<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAISegmentCreatorBundle\Service;

use Mautic\EmailBundle\Model\EmailModel;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Model\FieldModel;
use Mautic\LeadBundle\Model\ListModel;
use Mautic\PageBundle\Model\PageModel;
use MauticPlugin\MauticAIconnectionBundle\Service\LiteLLMService;

class SegmentCreatorService
{
    public function __construct(
        private LiteLLMService $liteLLMService,
        private ListModel $listModel,
        private FieldModel $fieldModel,
        private EmailModel $emailModel,
        private PageModel $pageModel,
    ) {
    }

    public function getAvailableModels(): array
    {
        try {
            return $this->liteLLMService->getAvailableModels();
        } catch (\Exception) {
            return ['GPT-3.5 Turbo' => 'gpt-3.5-turbo'];
        }
    }

    public function generate(string $prompt, string $model): int
    {
        $context = $this->buildContext();

        $systemPrompt = $this->buildSystemPrompt($context);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => 'Create a Mautic segment for: '.$prompt],
        ];

        $response = $this->liteLLMService->getChatCompletion($messages, [
            'model'       => $model,
            'max_tokens'  => 2000,
            'temperature' => 0.3,
        ]);

        $raw = trim($response['choices'][0]['message']['content'] ?? '');

        // Strip markdown code fences
        $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
        $raw = preg_replace('/\s*```\s*$/i', '', $raw);
        $raw = trim($raw);

        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['filters'])) {
            throw new \RuntimeException('AI returned an invalid segment structure. Raw response: '.substr($raw, 0, 300));
        }

        return $this->createSegment($data);
    }

    private function buildContext(): array
    {
        // Contact fields
        $fields    = $this->fieldModel->getRepository()->findBy(['object' => 'lead', 'isPublished' => true]);
        $fieldList = [];
        foreach ($fields as $field) {
            $fieldList[] = [
                'alias' => $field->getAlias(),
                'label' => $field->getLabel(),
                'type'  => $field->getType(),
            ];
        }

        // Emails
        $emailList = [];
        try {
            $emails = $this->emailModel->getRepository()->findBy(['isPublished' => true], ['name' => 'ASC'], 100);
            foreach ($emails as $email) {
                $emailList[] = ['id' => $email->getId(), 'name' => $email->getName()];
            }
        } catch (\Exception) {
        }

        // Pages
        $pageList = [];
        try {
            $pages = $this->pageModel->getRepository()->findBy(['isPublished' => true], ['title' => 'ASC'], 100);
            foreach ($pages as $page) {
                $pageList[] = ['id' => $page->getId(), 'name' => $page->getTitle(), 'url' => $page->getAlias()];
            }
        } catch (\Exception) {
        }

        return [
            'lead_fields' => $fieldList,
            'emails'      => $emailList,
            'pages'       => $pageList,
        ];
    }

    private function buildSystemPrompt(array $context): string
    {
        $fieldsJson = json_encode($context['lead_fields'], JSON_PRETTY_PRINT);
        $emailsJson = !empty($context['emails']) ? json_encode($context['emails'], JSON_PRETTY_PRINT) : '[]';
        $pagesJson  = !empty($context['pages']) ? json_encode($context['pages'], JSON_PRETTY_PRINT) : '[]';

        return <<<EOT
You are a Mautic segment builder. Generate a Mautic contact segment based on the user's description.

OUTPUT: Respond with ONLY valid JSON — no markdown, no explanation, no code fences.

JSON SCHEMA:
{
  "name": "Short descriptive segment name",
  "filters": [
    {
      "glue": "and",
      "field": "field_alias_here",
      "object": "lead",
      "type": "text",
      "operator": "=",
      "value": "some value",
      "display": null
    }
  ]
}

FILTER RULES:
- "glue": always "and" for the first filter; "and" or "or" for subsequent ones
- "object": use "lead" for contact field filters, "behaviors" for behavioral filters
- For behavioral filters, use these "field" values:
    - "lead_email_received" (type: "lead_email_received") — contact received a specific email; value = array of email IDs
    - "lead_email_read_count" (type: "number") — number of emails read; object: "behaviors"
    - "hit_url" (type: "text") — contact visited a URL; operator: "like"
    - "page_id" (type: "boolean") — contact visited a specific page; value = page ID

OPERATORS by type:
- text / textarea / email / url: "=", "!=", "like", "!like", "empty", "!empty", "regexp"
- number / score: "=", "!=", "gt", "gte", "lt", "lte", "empty", "!empty"
- boolean: "=" with value 1 (true) or 0 (false)
- select / country / region / timezone / locale: "=", "!=", "in", "!in", "empty", "!empty"
- datetime / date: "=", "!=", "gt", "gte", "lt", "lte", "between", "empty", "!empty"
- tags / lead_email_received: "in", "!in", "empty", "!empty"
- For "in" / "!in": value must be an array

AVAILABLE CONTACT FIELDS:
{$fieldsJson}

AVAILABLE EMAILS (use IDs for lead_email_received):
{$emailsJson}

AVAILABLE PAGES (use IDs for page_id):
{$pagesJson}

Pick the most appropriate fields and operators to match the user's description. Use multiple filters when needed.
EOT;
    }

    private function createSegment(array $data): int
    {
        $name    = trim($data['name'] ?? 'AI Generated Segment');
        $name    = '✨ '.$name;
        $alias   = $this->generateAlias($name);

        $segment = new LeadList();
        $segment->setName($name);
        $segment->setAlias($alias);
        $segment->setPublicName($name);
        $segment->setIsGlobal(true);
        $segment->setIsPublished(false);

        $filters = [];
        foreach ($data['filters'] as $i => $f) {
            $value = $f['value'] ?? null;

            $filters[] = [
                'glue'       => $i === 0 ? 'and' : ($f['glue'] ?? 'and'),
                'field'      => $f['field'],
                'object'     => $f['object'] ?? 'lead',
                'type'       => $f['type'],
                'operator'   => $f['operator'],
                'properties' => [
                    'filter'  => $value,
                    'display' => $f['display'] ?? null,
                ],
                'filter'  => $value,
                'display' => $f['display'] ?? null,
            ];
        }

        $segment->setFilters($filters);
        $this->listModel->saveEntity($segment);

        return (int) $segment->getId();
    }

    private function generateAlias(string $name): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));
        $slug = substr($slug, 0, 50);

        return $slug.'-'.substr(md5(uniqid('', true)), 0, 6);
    }
}
