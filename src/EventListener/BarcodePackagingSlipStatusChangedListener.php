<?php
/**
 * Copyright (C) 2022  Jaap Jansma (jaap.jansma@civicoop.org)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace Krabo\IsotopePackagingSlipBarcodeScannerDHLBundle\EventListener;

use Contao\Email;
use Contao\System;
use Isotope\Model\Shipping;
use Krabo\IsotopePackagingSlipBarcodeScannerBundle\Event\FormBuilderEvent;
use Krabo\IsotopePackagingSlipBarcodeScannerBundle\Event\PackagingSlipStatusChangedEvent;
use Krabo\IsotopePackagingSlipDHLBundle\Factory\DHLConnectionFactoryInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\RequestStack;

class BarcodePackagingSlipStatusChangedListener implements \Symfony\Component\EventDispatcher\EventSubscriberInterface {

  /**
   * @var \Krabo\IsotopePackagingSlipDHLBundle\Factory\DHLConnectionFactoryInterface
   */
  protected $connectionFactory;

  /**
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  public function __construct(DHLConnectionFactoryInterface $connectionFactory, RequestStack $requestStack) {
    $this->connectionFactory = $connectionFactory;
    $this->requestStack = $requestStack;
  }

  /**
   * Returns an array of event names this subscriber wants to listen to.
   *
   * The array keys are event names and the value can be:
   *
   *  * The method name to call (priority defaults to 0)
   *  * An array composed of the method name to call and the priority
   *  * An array of arrays composed of the method names to call and respective
   *    priorities, or 0 if unset
   *
   * For instance:
   *
   *  * ['eventName' => 'methodName']
   *  * ['eventName' => ['methodName', $priority]]
   *  * ['eventName' => [['methodName1', $priority], ['methodName2']]]
   *
   * The code must not depend on runtime state as it will only be called at
   * compile time. All logic depending on runtime state must be put into the
   * individual methods handling the events.
   *
   * @return array<string, mixed> The event names to listen to
   */
  public static function getSubscribedEvents() {
    return [
      PackagingSlipStatusChangedEvent::EVENT_STATUS_SHIPPED => 'onStatusShipped',
      FormBuilderEvent::EVENT_NAME => 'onFormBuilder',
    ];
  }

  public function onStatusShipped(PackagingSlipStatusChangedEvent $event) {
    $submittedData = $event->getSubmittedData();
    if (empty($submittedData['email'])) {
      return;
    }
    $recipient = $submittedData['email'];
    $format = 'ZPL';
    if (isset($submittedData['email_format'])) {
      $format = $submittedData['email_format'];
    }
    $this->requestStack->getCurrentRequest()->getSession()->set('krabo.isotope-packaging-slip-barcode-scanner-dhl.email', $recipient);
    $this->requestStack->getCurrentRequest()->getSession()->set('krabo.isotope-packaging-slip-barcode-scanner-dhl.email_format', $format);
    $packagingSlip = $event->getPackagingSlip();
    $shippingMethod = Shipping::findByPk($packagingSlip->shipping_id);
    if (in_array($shippingMethod->type, ['isopackagingslip_dhl'])) {
      if (!$packagingSlip->dhl_id) {
        $this->connectionFactory->createParcel($packagingSlip);
      }
      $client = $this->connectionFactory->getClient();
      $requestHeaders = [
        'Authorization' => 'Bearer '.$client->authentication->getAccessToken()->token,
        'Accept' => 'application/pdf'
      ];
      if ($format == 'ZPL') {
        $requestHeaders['Accept'] = 'application/zpl';
      }
      $response = $client->performHttpCall('GET', 'labels/'.$packagingSlip->dhl_id, null, $requestHeaders);
      if ($response->getStatusCode() == 200) {
        $labelContents = $response->getBody()->getContents();
        $email = new Email();
        $email->subject = 'PDF DHL Barcode';
        if ($format =='ZPL') {
          $email->text = $labelContents;
        } else {
          $email->text = 'See attachment';
          $email->attachFileFromString($labelContents, 'barcode.pdf', 'application/pdf');
        }
        $email->sendTo($recipient);
      } else {
        throw new \RuntimeException('Could not send Barcode to the printers email address');
      }
    }
  }

  /**
   * @param \Krabo\IsotopePackagingSlipBarcodeScannerBundle\Event\FormBuilderEvent $event
   * @return void
   */
  public function onFormBuilder(FormBuilderEvent $event) {
    $recipient = '';
    if ($this->requestStack->getCurrentRequest()->getSession()->has('krabo.isotope-packaging-slip-barcode-scanner-dhl.email')) {
      $recipient = $this->requestStack->getCurrentRequest()->getSession()->get('krabo.isotope-packaging-slip-barcode-scanner-dhl.email');
    }
    $format = 'ZPL';
    if ($this->requestStack->getCurrentRequest()->getSession()->has('krabo.isotope-packaging-slip-barcode-scanner-dhl.email_format')) {
      $format = $this->requestStack->getCurrentRequest()->getSession()->get('krabo.isotope-packaging-slip-barcode-scanner-dhl.email_format');
    }

    $event->formBuilder->add('email', EmailType::class, [
      'label' => 'Email',
      'attr' => [
        'class' => 'tl_text',
      ],
      'row_attr' => [
        'class' => 'widget'
      ],
    ]);
    $event->formBuilder->get('email')->setData($recipient);
    $event->formBuilder->add('email_format', ChoiceType::class, [
      'label' => 'Email format',
      'choices' => [
        'ZPL' => 'ZPL',
        'PDF' => 'PDF',
      ],
      'attr' => [
        'class' => 'tl_radio',
      ],
      'row_attr' => [
        'class' => 'tl_radio_container'
      ],
      'expanded' => true,
      'multiple' => false,
    ]);
    $event->formBuilder->get('email_format')->setData($format);
    $event->additionalWidgets[] = 'email';
    $event->additionalWidgets[] = 'email_format';
  }


}