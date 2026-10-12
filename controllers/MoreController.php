<?php
declare(strict_types=1);

/**
 * The "More" tab.
 *
 * Mobile v2 has no drawer, so this is the escape hatch to everything that does
 * not earn a permanent slot in the five-item tab bar.
 */
class MoreController
{
    public function index(): void
    {
        requireLogin();
        $pageTitle = 'More';
        require __DIR__ . '/../views/more/index.php';
    }
}
