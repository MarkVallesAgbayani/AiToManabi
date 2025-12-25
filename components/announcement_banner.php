<?php
require_once __DIR__ . '/../config/database.php';

// Fetch active announcement
$stmt = $pdo->prepare("SELECT * FROM announcement_banner WHERE is_published = 1 AND (start_date IS NULL OR start_date <= NOW()) AND (end_date IS NULL OR end_date >= NOW()) ORDER BY updated_at DESC LIMIT 1");
$stmt->execute();
$announcement = $stmt->fetch(PDO::FETCH_ASSOC);

// Only show banner if there's a published announcement
if ($announcement): ?>
    <div class="announcement-banner-container" 
         style="background-color: <?php echo $announcement['background_color']; ?>;">
        <div class="announcement-banner">
            <div class="announcement-content" style="color: <?php echo $announcement['text_color']; ?>">
                <?php echo htmlspecialchars($announcement['content']); ?>
                <?php if (!empty($announcement['button_text']) && !empty($announcement['button_url'])): ?>
                    <a href="<?php echo htmlspecialchars($announcement['button_url']); ?>" 
                       class="announcement-button"
                       style="background-color: <?php echo $announcement['button_color']; ?>">
                        <?php if (!empty($announcement['button_icon'])): ?>
                            <i class="<?php echo htmlspecialchars($announcement['button_icon']); ?>"></i>
                        <?php endif; ?>
                        <?php echo htmlspecialchars($announcement['button_text']); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <style>
        .announcement-banner-container {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 60;
            padding: 0.5rem 0;
            overflow: hidden;
            transform: translateZ(0);
        }

        .announcement-banner {
            display: flex;
            white-space: nowrap;
            animation: scroll 15s linear infinite;
        }

        .announcement-banner:hover {
            animation-play-state: paused;
        }

        .announcement-content {
            display: inline-block;
            padding: 0.25rem 1rem;
            color: #1a1a1a;
            font-weight: 500;
            font-size: 0.875rem;
            margin-right: 4rem;
            white-space: nowrap;
        }

        @keyframes scroll {
            0% {
                transform: translateX(100%);
            }
            100% {
                transform: translateX(-100%);
            }
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .announcement-content {
                font-size: 0.75rem;
                padding: 0.25rem 0.5rem;
            }
        }

        /* Optional: Add text shadow for better readability */
        .announcement-content {
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }

        .announcement-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            color: white;
            font-weight: 500;
            text-decoration: none;
            margin-left: 1rem;
            transition: opacity 0.2s ease;
        }

        .announcement-button:hover {
            opacity: 0.9;
        }

        .announcement-button i {
            font-size: 0.875rem;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .announcement-button {
                padding: 0.125rem 0.5rem;
                font-size: 0.75rem;
            }
        }
    </style>
<?php endif; ?> 