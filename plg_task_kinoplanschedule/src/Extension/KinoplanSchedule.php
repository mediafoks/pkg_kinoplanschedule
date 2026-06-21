<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Task.KinoplanSchedule
 *
 * @copyright   (C) 2023 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Task\KinoplanSchedule\Extension;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * A task plugin. Offers 2 task routines Invalidate Expired Consents and Remind Expired Consents
 * {@see ExecuteTaskEvent}.
 *
 * @since 5.0.0
 */
final class KinoplanSchedule extends CMSPlugin implements SubscriberInterface
{
    use TaskPluginTrait;

    /**
     * @var string[]
     * @since 5.0.0
     */
    private const TASKS_MAP = [
        'kinoplan.schedule' => [
            'langConstPrefix' => 'PLG_TASK_KINOPLANSCHEDULE',
            'method'          => 'kinoplanSchedule',
            'form'            => 'kinoplanscheduleForm',
        ],
    ];

    /**
     * @var boolean
     *
     * @since 5.0.0
     */
    protected $autoloadLanguage = true;

    /**
     * @inheritDoc
     *
     * @return string[]
     *
     * @since 5.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList'    => 'advertiseRoutines',
            'onExecuteTask'        => 'standardRoutineHandler',
            'onContentPrepareForm' => 'enhanceTaskItemForm',
        ];
    }

    /**
     * Method to send the remind for privacy consents renew.
     *
     * @param   ExecuteTaskEvent  $event  The `onExecuteTask` event.
     *
     * @return integer  The routine exit code.
     *
     * @since  5.0.0
     * @throws \Exception
     */
    private function kinoplanSchedule(ExecuteTaskEvent $event): int
    {
        // Load the parameters.
        $cinemaId = (int) $event->getArgument('params')->cinema_id ?? 10296;
        $apiKey = (string) $event->getArgument('params')->api_key ?? null;
        $kinopoiskApiKey = (string) $event->getArgument('params')->kinopoisk_api_key ?? null;
        /**
         * Шаблон ссылки покупки
         */
        $ticketTemplate = 'https://kinowidget.kinoplan.ru/seats/{cinema_id}/{film_id}/{session_id}';

        $cacheDir = JPATH_ROOT . '/media/kinoplanschedule_cache';

        if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);

        try {
            /**
             * TOKEN
             */
            $tokenResponse = file_get_contents('https://ts.kinoplan24.ru/api/auth/token?api_key=' . urlencode($apiKey));
            $tokenData = json_decode($tokenResponse, true);
            $token = $tokenData['request_token'] ?? null;

            if (!$token) {
                file_put_contents($cacheDir . '/scheduler-error.log', 'No token');
                return Status::KNOCKOUT;
            }

            /**
             * Запрос расписания Kinoplan
             */
            $ch = curl_init();

            curl_setopt_array($ch, [

                CURLOPT_URL => 'https://ts.kinoplan24.ru/api/schedule',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [

                    'REQUEST-TOKEN: ' . $token,
                    'Accept: application/json'
                ],

                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);

            curl_close($ch);

            if (!$response || $httpCode !== 200) {

                file_put_contents($cacheDir . '/scheduler-error.log', 'HTTP: ' . $httpCode . PHP_EOL . $curlError);
                return Status::KNOCKOUT;
            }

            $data = json_decode($response, true);

            if (!$data || empty($data['films'])) {
                file_put_contents($cacheDir . '/scheduler-error.log', 'Invalid JSON');
                return Status::KNOCKOUT;
            }
            file_put_contents($cacheDir . '/kinoplan-response.log', $response);

            /**
             * Собираем фильмы
             */
            $movies = [];

            foreach ($data['films'] as $film) {
                $movie = [
                    'id' => $film['kinoplan_id'],
                    'title' => $film['name'],
                    'age' => $film['rate'],
                    'poster' => 'https://placehold.co/400x600?text=' . urlencode($film['name']),
                    'sessions' => []
                ];
                if ((int) $film['length'] !== 0) $movie['length'] = $film['length'];

                /**
                 * Рoster
                 */
                try {
                    $picCh = curl_init();

                    curl_setopt_array($picCh, [

                        CURLOPT_URL => 'https://ts.kinoplan24.ru/api/release/' . $movie['id'] . '/full',
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_HTTPHEADER => [
                            'REQUEST-TOKEN: ' . $token,
                            'Accept: application/json'
                        ],

                        CURLOPT_TIMEOUT => 30,
                        CURLOPT_CONNECTTIMEOUT => 10,
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_SSL_VERIFYHOST => false,
                    ]);

                    $picResponse = curl_exec($picCh);
                    $picHttpCode = curl_getinfo($picCh, CURLINFO_HTTP_CODE);
                    $picCurlError = curl_error($picCh);

                    curl_close($picCh);

                    if (!$picResponse || (int) $picHttpCode === 200) {
                        $p_data = json_decode($picResponse, true);
                        $movie['poster'] = $p_data['cover'] ?? $p_data['cover_large'] ?? $p_data['cover_middle'];
                    }
                } catch (\Throwable $e) {
                }

                /**
                 * Сеансы
                 */
                foreach ($data['schedule'] as $session) {
                    if ($session['film_id'] != $film['kinoplan_id']) continue;

                    /**
                     * Генерация ссылки покупки
                     */
                    $buyUrl =
                        str_replace(
                            [
                                '{cinema_id}',
                                '{film_id}',
                                '{session_id}',
                            ],
                            [
                                $cinemaId,
                                $film['kinoplan_id'],
                                $session['id'],
                            ],
                            $ticketTemplate
                        );

                    /**
                     * Добавляем сеанс
                     */
                    if ((int) $session['sale']['price_min'] === 0) continue;
                    $movie['sessions'][] = [
                        'id' => $session['id'],
                        'time' => $session['start'],
                        'date' => $session['date'],
                        'hall' => $session['hall_id'],
                        'price' => $session['sale']['price_min'] ?? 0,
                        'buy_url' => $buyUrl,
                        'is_open' => $session['sale']['is_open'] ?? false,
                        'is_close_online_sale' => $session['sale']['is_close_online_sale'] ?? true,
                    ];
                }

                /**
                 * Пропускаем фильмы без сеансов
                 */
                if (empty($movie['sessions'])) continue;

                $movies[] = $movie;
            }

            /**
             * Сохраняем cache
             */
            file_put_contents(
                $cacheDir . '/schedule.json',
                json_encode($movies, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            );
            // СБРОС PHP FILE CACHE
            clearstatcache(true, $cacheDir . '/schedule.json');
            /**
             * Успешный лог
             */
            file_put_contents($cacheDir . '/scheduler-success.log', date('Y-m-d H:i:s') . ' Cache updated');

            return Status::OK;
        } catch (\Throwable $e) {
            file_put_contents($cacheDir . '/scheduler-error.log', $e->getMessage() . PHP_EOL . $e->getTraceAsString());

            return Status::KNOCKOUT;
        }
    }
}
