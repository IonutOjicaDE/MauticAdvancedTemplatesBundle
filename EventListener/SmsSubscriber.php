<?php

namespace MauticPlugin\MauticAdvancedTemplatesBundle\EventListener;

use Mautic\CampaignBundle\Entity\Lead;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Mautic\SmsBundle\SmsEvents;
use Mautic\SmsBundle\Event as Events;
use Mautic\EmailBundle\Helper\PlainTextHelper;
use Mautic\CoreBundle\Exception as MauticException;
use Mautic\CoreBundle\Helper\EmojiHelper;
use Mautic\LeadBundle\Model\LeadModel;
use Psr\Log\LoggerInterface;
use Monolog\Logger;
use MauticPlugin\MauticAdvancedTemplatesBundle\Helper\TemplateProcessor;
use MauticPlugin\MauticAdvancedTemplatesBundle\Helper\FormSubmission;

/**
 * Class SmsSubscriber.
 */
class SmsSubscriber implements EventSubscriberInterface
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
        $this->templateProcessor = $templateProcessor;
        $this->leadModel = $leadModel;
        $this->logger = $logger;
        $this->formSubmissionHelper = $formSubmissionHelper;
    }
    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            SmsEvents::SMS_ON_SEND => ['onSmsGenerate', 300],
            // I dont know how to do this without editing core.
            // since there does not seem to be a similar way to call it yet.
            // SmsEvents::SMS_ON_DISPLAY => ['onSmsGenerate', 0],
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

    /**
     * Search and replace tokens with content
     *
     * @param Events\SmsSendEvent $event
     * @throws \Throwable
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Syntax
     */
    public function onSmsGenerate(Events\SmsSendEvent $event)
    {
        $this->logger->info('onSmsGenerate MauticAdvancedTemplatesBundle\SmsSubscriber');

        $content = $event->getContent();

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

        $content = $this->templateProcessor->processTemplate($content, $lead, $formData);
        $content = EmojiHelper::toEmoji($content, 'short');
        $event->setContent($content);
    }
} 
