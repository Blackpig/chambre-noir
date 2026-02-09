<?php

namespace BlackpigCreatif\ChambreNoir\Conversions;

class SocialImageConversion extends BaseConversion
{
    /**
     * Higher quality for social media cards
     * These images are often the first impression of shared content
     */
    protected int $defaultQuality = 90;

    /**
     * Crop fit for social media
     * Social platforms require specific dimensions
     */
    protected string $defaultFit = 'crop';

    /**
     * Define social media image conversions
     * Optimized for Open Graph and Twitter cards
     */
    protected function define(): array
    {
        return [
            // Open Graph image (Facebook, LinkedIn, etc.)
            // Recommended: 1200x630px
            'og' => [
                'width' => 1200,
                'height' => 630,
            ],

            // Twitter card image
            // Recommended: 1200x600px for summary_large_image
            'twitter' => [
                'width' => 1200,
                'height' => 600,
            ],
        ];
    }

    /**
     * Social images don't need responsive configuration
     * They're used as-is by social media platforms
     */
    public function getResponsiveConfig(): array
    {
        return [
            'default' => 'og',
            'srcset' => [
                'og' => false,
                'twitter' => false,
            ],
            'picture' => [
                'og' => null,
            ],
            'sizes' => null,
        ];
    }
}
