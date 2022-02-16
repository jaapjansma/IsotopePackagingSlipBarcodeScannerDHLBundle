<?php

namespace Krabo\IsotopePackagingSlipBarcodeScannerDHLBundle\ContaoManager;

use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;

class Plugin implements BundlePluginInterface {

  public function getBundles(ParserInterface $parser): array {
    return [
      BundleConfig::create('Krabo\IsotopePackagingSlipBarcodeScannerDHLBundle\IsotopePackagingSlipBarcodeScannerDHLBundle')
        ->setLoadAfter([
          'isotope',
          'Krabo\IsotopePackagingSlipBarcodeScannerBundle\IsotopePackagingSlipBarcodeScannerBundle',
          'Krabo\IsotopePackagingSlipDHLBundle\IsotopePackagingSlipDHLBundle',
        ]),
    ];
  }

}