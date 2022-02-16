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
use Isotope\Model\Shipping;
use Krabo\IsotopePackagingSlipDHLBundle\Factory\DHLConnectionFactoryInterface;

class BarcodePackagingSlipStatusChangedListener implements \Symfony\Component\EventDispatcher\EventSubscriberInterface {

  /**
   * @var \Krabo\IsotopePackagingSlipDHLBundle\Factory\DHLConnectionFactoryInterface
   */
  protected $connectionFactory;

  public function __construct(DHLConnectionFactoryInterface $connectionFactory) {
    $this->connectionFactory = $connectionFactory;
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
      \Krabo\IsotopePackagingSlipBarcodeScannerBundle\Event\PackagingSlipStatusChangedEvent::EVENT_STATUS_SHIPPED => 'onStatusShipped',
    ];
  }

  public function onStatusShipped(\Krabo\IsotopePackagingSlipBarcodeScannerBundle\Event\PackagingSlipStatusChangedEvent $event) {
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
      $response = $client->performHttpCall('GET', 'labels/'.$packagingSlip->dhl_id, null, $requestHeaders);
      if ($response->getStatusCode() == 200) {
        $pdf = $response->getBody()->getContents();
        $email = new Email();
        $email->subject = 'PDF DHL Barcode';
        $email->text = 'See attachment';
        $email->attachFileFromString($pdf, 'barcode.pdf', 'application/pdf');
        $email->sendTo('tkraus@krabo.nl');
      }
    }
  }


}