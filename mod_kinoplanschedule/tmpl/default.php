<?php

defined('_JEXEC') or die;

?>

<div class="container py-4">
    <div class="row">
        <?php foreach ($list as $movie) : ?>
            <div class="col-12 col-sm-6 col-lg-4 col-xl-3 mb-4">
                <div
                    class="card h-100 shadow-sm border-0"
                    itemscope
                    itemtype="https://schema.org/Event">
                    <img
                        src="<?= htmlspecialchars($movie['poster']) ?>"
                        class="card-img-top"
                        alt="<?= htmlspecialchars($movie['title']) ?>"
                        loading="lazy"
                        style="
                            aspect-ratio:2/3;
                            object-fit:cover;
                        ">
                    <div class="card-body d-flex flex-column">
                        <h5
                            class="card-title"
                            itemprop="name">
                            <?= htmlspecialchars($movie['title']) ?>
                        </h5>
                        <div class="mb-2 text-muted">
                            <?= htmlspecialchars($movie['age']) ?>
                            <?= $movie['length'] ? '· ' . $movie['length'] . 'мин' : ''; ?>
                        </div>
                        <div class="mt-auto">
                            <?php foreach ($movie['sessions'] as $session) : ?>
                                <div class="mb-2">
                                    <a
                                        href="<?= htmlspecialchars($session['buy_url']) ?>"
                                        class="btn btn-outline-primary btn-sm kp-session-btn"
                                        target="_blank"
                                        rel="noopener">

                                        <?= htmlspecialchars($session['date']) ?>

                                        <?= htmlspecialchars($session['time']) ?>

                                        —

                                        <?= (int) $session['price'] ?> ₽

                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>