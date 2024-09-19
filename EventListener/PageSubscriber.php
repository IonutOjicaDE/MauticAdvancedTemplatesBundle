<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticAdvancedTemplatesBundle\EventListener;

use Mautic\PageBundle\Event\PageDisplayEvent;
use Mautic\PageBundle\PageEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Mautic\CoreBundle\Exception as MauticException;
use Mautic\LeadBundle\Tracker\ContactTracker;
use Mautic\LeadBundle\Model\LeadModel;
use Psr\Log\LoggerInterface;
use Monolog\Logger;
use MauticPlugin\MauticAdvancedTemplatesBundle\Helper\TemplateProcessor;
use MauticPlugin\MauticAdvancedTemplatesBundle\Helper\FormSubmission;

class PageSubscriber implements EventSubscriberInterface
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
     * @var Logger
     */
    protected $logger;

    /**
     * @var FormSubmission
     */
    protected $formSubmissionHelper;

    /**
     * @var ContactTracker
     */
    private $contactTracker;

    /**
     * EmailSubscriber constructor.
     *
     * @param TemplateProcessor $templateProcessor
     * @param LeadModel $leadModel
     * @param Logger $logger
     * @param FormSubmission $formSubmissionHelper
     * @param ContactTracker $contactTracker
     */
    public function __construct(TemplateProcessor $templateProcessor, LeadModel $leadModel, Logger $logger, FormSubmission $formSubmissionHelper, ContactTracker $contactTracker)
    {
        $this->templateProcessor    = $templateProcessor;
        $this->leadModel            = $leadModel;
        $this->logger               = $logger;
        $this->formSubmissionHelper = $formSubmissionHelper;
        $this->contactTracker       = $contactTracker;
    }    

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            PageEvents::PAGE_ON_DISPLAY   => ['onPageDisplay', 0],
        ];
    }

    /**
     * Try to retrieve the current form values of the active lead 
     * 
     * @param integer $leadId  
     * @param integer $emailId
     */
    private function getFormData($leadId)
    {
        return $this->formSubmissionHelper->getFormData($leadId);
    }    


    public function onPageDisplay(PageDisplayEvent $event)
    {
        $this->logger->info('onPageDisplay MauticAdvancedTemplatesBundle\PageSubscriber');

        $content = $event->getContent();
        if (empty($content)) {
            return;
        }

        $formData = [];
        $lead = $this->contactTracker->getContact();
        if($lead)
        {
            $leadCredentials = $lead->getProfileFields();
            $formData = $this->getFormData($leadCredentials['id']);
            $leadmodel = $this->leadModel->getEntity($leadCredentials['id']);
            $leadCredentials['tags'] = [];
            if ($leadmodel && count($leadmodel->getTags()) > 0) {
                foreach ($leadmodel->getTags() as $tag) {
                    $leadCredentials['tags'][] = $tag->getTag();
                }
            }
        }

        $content = $this->templateProcessor->processTemplate($content, $leadCredentials, $formData);

        $event->setContent($content);
    }
}