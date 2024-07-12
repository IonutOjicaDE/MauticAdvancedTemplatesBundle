<?php

namespace MauticPlugin\MauticAdvancedTemplatesBundle\EventListener;

//use Mautic\CampaignBundle\Entity\Lead;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Mautic\DynamicContentBundle\DynamicContentEvents;
use Mautic\CoreBundle\Event\TokenReplacementEvent;
use Mautic\CoreBundle\Exception as MauticException;
use Mautic\LeadBundle\Model\LeadModel;
//use Mautic\LeadBundle\Entity\Lead;
use Psr\Log\LoggerInterface;
use Monolog\Logger;
use MauticPlugin\MauticAdvancedTemplatesBundle\Helper\TemplateProcessor;
use MauticPlugin\MauticAdvancedTemplatesBundle\Helper\FormSubmission;

/**
 * Class DynamicContentSubscriber
 */
class DynamicContentSubscriber implements EventSubscriberInterface
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
    static public function getSubscribedEvents()
    {
        return array(
            DynamicContentEvents::TOKEN_REPLACEMENT => array('onTokenReplacement', 0)
        );
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
     * @param TokenReplacementEvent $event
     */
    public function onTokenReplacement(TokenReplacementEvent $event)
    {
        $this->logger->info('onTokenReplacement MauticAdvancedTemplatesBundle\DynamicContentSubscriber');

        /** @var Lead $lead */
        $content = $event->getContent();

        if (!$content) {
            return;
        }

        $lead = $event->getLead();
        $leadCredentials = $lead->getProfileFields();
        $formData = $this->getFormData($leadCredentials['id']);

        $content = $this->templateProcessor->processTemplate($content, $leadCredentials, $formData);        

        $event->setContent($content);
    }
}
