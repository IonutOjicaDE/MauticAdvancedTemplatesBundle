<?php

namespace MauticPlugin\MauticAdvancedTemplatesBundle\EventListener;

use Mautic\CampaignBundle\Entity\Lead;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Event as Events;
use Mautic\EmailBundle\Helper\PlainTextHelper;
use Mautic\CoreBundle\Exception as MauticException;
use Mautic\CoreBundle\Helper\EmojiHelper;
use Mautic\LeadBundle\Model\LeadModel;
use Psr\Log\LoggerInterface;
use Monolog\Logger;
use MauticPlugin\MauticAdvancedTemplatesBundle\Helper\TemplateProcessor;
use MauticPlugin\MauticAdvancedTemplatesBundle\Helper\FormSubmission;

/**
 * Class EmailSubscriber.
 */
class EmailSubscriber implements EventSubscriberInterface
{
    /**
     * @var TemplateProcessor $templateProcessor ;
     */
    protected $templateProcessor;

    /**
     * @var LeadModel $leadModel ;
     */
    protected $leadModel;

    /**
     * @var LoggerInterface $logger ;
     */
    protected $logger;

    /**
     * @var FormSubmission $formSubmissionHelper ;
     */
    protected $formSubmissionHelper;

    /**
     * EmailSubscriber constructor.
     *
     * @param TemplateProcessor $templateProcessor
     * @param LeadModel $leadModel
     * @param Logger $logger
     * @param FormSubmission $formSubmissionHelper
     */
    public function __construct(TemplateProcessor $templateProcessor, LeadModel $leadModel, Logger $logger, FormSubmission $formSubmissionHelper)
    {
        $this->templateProcessor    = $templateProcessor;
        $this->leadModel            = $leadModel;
        $this->logger               = $logger;
        $this->formSubmissionHelper = $formSubmissionHelper;
    }
    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            EmailEvents::EMAIL_ON_SEND => ['onEmailGenerate', 300],
            EmailEvents::EMAIL_ON_DISPLAY => ['onEmailGenerate', 300],
        ];
    }

    /**
     * Try to retrieve the current form values of the active lead 
     * 
     * @param integer $leadId
     */
    private function getFormData($leadId)
    {
        return $this->formSubmissionHelper->getFormData($leadId);
    }

    private function getProperties(Events\EmailSendEvent $event) {
        $tokens = [];

        if (!$event->getEmail()) {
            return [
                'subject' => $event->getSubject(),
                'content' => $event->getContent(),
                'tokens' => $tokens,
            ];
        }

        $email = $event->getEmail();

        $subject = $email->getSubject();
        $content = $email->getCustomHtml();
        $dynamic = $email->getDynamicContent();

        foreach ($dynamic as $prop) {
            $tokens[$prop['tokenName']] = $prop['content'];
        }

        //Add arbritrary tokens when using the email send api
        $originalTokens = $event->getTokens();
        foreach($originalTokens as $k => $v) {
            $tokens[preg_replace('/^{(.*)}$/', '${1}', $k)] = $v;
        }

        return [
            'subject' => $subject,
            'content' => $content,
            'tokens' => $tokens,
        ];
    }

    /**
     * Search and replace tokens with content
     *
     * @param Events\EmailSendEvent $event
     * @throws \Throwable
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Syntax
     */
    public function onEmailGenerate(Events\EmailSendEvent $event)
    {
        $this->logger->info('onEmailGenerate MauticAdvancedTemplatesBundle\EmailSubscriber');

        if ($event->isDynamicContentParsing()) {
            return;
        }

        $props = $this->getProperties($event);

        $formData = [];               
        $lead = $event->getLead();
        $leadmodel = $this->leadModel->getEntity($lead['id']);
        $lead['tags'] = [];
        if ($leadmodel && count($leadmodel->getTags()) > 0) {
            foreach ($leadmodel->getTags() as $tag) {
                $lead['tags'][] = $tag->getTag();
            }
        }
        if(is_array($lead)){
            $formData = $this->getFormData($lead['id']);
        }
        
        $subject = $this->templateProcessor->processTemplate($props['subject'], $lead, $formData);
        $subject = EmojiHelper::toEmoji($subject, 'short');
        $event->setSubject($subject);

        $content = $this->templateProcessor->processTemplate($props['content'], $lead, $formData, $props['tokens']);
        $content = $this->templateProcessor->addTrackingPixel($content);
        $content = EmojiHelper::toEmoji($content, 'short');
        $event->setContent($content);

        if ( empty( trim($event->getPlainText()) ) ) {
            
            $event->setPlainText( (new PlainTextHelper($content))->getText() );
        }
    }
}
