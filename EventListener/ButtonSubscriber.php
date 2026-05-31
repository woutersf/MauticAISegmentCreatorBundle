<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAISegmentCreatorBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Twig\Helper\ButtonHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouterInterface;

class ButtonSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RouterInterface $router,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_BUTTONS => ['injectGenerateButton', 0],
        ];
    }

    public function injectGenerateButton(CustomButtonEvent $event): void
    {
        if (ButtonHelper::LOCATION_PAGE_ACTIONS !== $event->getLocation()) {
            return;
        }

        [$currentRoute] = $event->getRoute(true);
        if ('mautic_segment_index' !== $currentRoute) {
            return;
        }

        $event->addButton([
            'attr' => [
                'href'  => $this->router->generate('mautic_ai_segment_creator_create'),
                'class' => 'btn btn-default btn-sm btn-nospin',
            ],
            'iconClass' => 'ri-sparkling-line',
            'btnText'   => 'Generate with AI',
            'priority'  => 50,
        ]);
    }
}
