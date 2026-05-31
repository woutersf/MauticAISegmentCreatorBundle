<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAISegmentCreatorBundle\Integration;

use Mautic\PluginBundle\Integration\AbstractIntegration;

class AiSegmentCreatorIntegration extends AbstractIntegration
{
    public function getName(): string
    {
        return 'AiSegmentCreator';
    }

    public function getDisplayName(): string
    {
        return 'AI Segment Creator';
    }

    public function getAuthenticationType(): string
    {
        return 'none';
    }

    public function getRequiredKeyFields(): array
    {
        return [];
    }
}
