<?php

declare(strict_types=1);

return [
    'name'        => 'AI Segment Creator',
    'description' => 'Generate Mautic contact segments using AI — describe your audience and get a ready-to-use segment with filters.',
    'version'     => '1.0.0',
    'author'      => 'Dropsolid',

    'routes' => [
        'main' => [
            'mautic_ai_segment_creator_create' => [
                'path'       => '/ai-segment-creator/create',
                'controller' => 'MauticPlugin\MauticAISegmentCreatorBundle\Controller\SegmentCreatorController::createAction',
            ],
        ],
        'api' => [],
    ],

    'menu' => [
        'main' => [
            'mautic.ai_segment_creator.menu' => [
                'route'     => 'mautic_ai_segment_creator_create',
                'iconClass' => 'ri-sparkling-line',
                'id'        => 'mautic_ai_segment_creator_create',
                'access'    => 'lead:lists:full',
                'priority'  => 55,
                'parent'    => 'mautic.lead.segments',
            ],
        ],
    ],

    'services' => [
        'events' => [
            'mautic.ai_segment_creator.button_subscriber' => [
                'class'     => \MauticPlugin\MauticAISegmentCreatorBundle\EventListener\ButtonSubscriber::class,
                'arguments' => [
                    'router',
                ],
            ],
        ],
        'other' => [
            'mautic.ai_segment_creator.service' => [
                'class'     => \MauticPlugin\MauticAISegmentCreatorBundle\Service\SegmentCreatorService::class,
                'arguments' => [
                    'mautic.ai_connection.service.litellm',
                    'mautic.lead.model.list',
                    'mautic.lead.model.field',
                    'mautic.email.model.email',
                    'mautic.page.model.page',
                ],
            ],
        ],
        'integrations' => [
            'mautic.integration.ai_segment_creator' => [
                'class'     => \MauticPlugin\MauticAISegmentCreatorBundle\Integration\AiSegmentCreatorIntegration::class,
                'arguments' => [
                    'event_dispatcher',
                    'mautic.helper.cache_storage',
                    'doctrine.orm.entity_manager',
                    'request_stack',
                    'router',
                    'translator',
                    'monolog.logger.mautic',
                    'mautic.helper.encryption',
                    'mautic.lead.model.lead',
                    'mautic.lead.model.company',
                    'mautic.helper.paths',
                    'mautic.core.model.notification',
                    'mautic.lead.model.field',
                    'mautic.plugin.model.integration_entity',
                    'mautic.lead.model.dnc',
                    'mautic.lead.field.fields_with_unique_identifier',
                ],
            ],
        ],
    ],
];
