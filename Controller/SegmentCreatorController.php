<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAISegmentCreatorBundle\Controller;

use Mautic\CoreBundle\Controller\FormController;
use MauticPlugin\MauticAISegmentCreatorBundle\Service\SegmentCreatorService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SegmentCreatorController extends FormController
{
    public static function getSubscribedServices(): array
    {
        return array_merge(parent::getSubscribedServices(), [
            'mautic.ai_segment_creator.service' => SegmentCreatorService::class,
        ]);
    }

    public function createAction(Request $request): Response
    {
        /** @var SegmentCreatorService $service */
        $service = $this->container->get('mautic.ai_segment_creator.service');

        $models        = $service->getAvailableModels();
        $error         = null;
        $prompt        = '';
        $selectedModel = array_values($models)[0] ?? 'gpt-3.5-turbo';

        if ($request->isMethod('POST')) {
            $prompt        = trim($request->request->get('prompt', ''));
            $selectedModel = $request->request->get('model', $selectedModel);

            if ('' === $prompt) {
                $error = 'Please describe the segment you want to create.';
            } else {
                try {
                    $segmentId = $service->generate($prompt, $selectedModel);

                    return $this->redirectToRoute('mautic_segment_action', [
                        'objectAction' => 'edit',
                        'objectId'     => $segmentId,
                    ]);
                } catch (\Exception $e) {
                    $error = 'AI generation failed: '.$e->getMessage();
                }
            }
        }

        return $this->delegateView([
            'viewParameters'  => [
                'models'        => $models,
                'error'         => $error,
                'prompt'        => $prompt,
                'selectedModel' => $selectedModel,
            ],
            'contentTemplate' => '@MauticAISegmentCreator/Creator/create.html.twig',
            'passthroughVars' => [
                'activeLink'    => '#mautic_ai_segment_creator_create',
                'mauticContent' => 'ai_segment_creator',
                'route'         => $this->generateUrl('mautic_ai_segment_creator_create'),
            ],
        ]);
    }
}
