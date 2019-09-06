<?php

namespace HeidelPayment\Subscribers\Frontend;

use Doctrine\Common\Collections\ArrayCollection;
use Enlight\Event\SubscriberInterface;
use EventArgs as EventArgs;
use Shopware\Components\Theme\LessDefinition;

class Template implements SubscriberInterface
{
    /** @var string */
    private $pluginDir;

    public function __construct(string $pluginDir)
    {
        $this->pluginDir = $pluginDir;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'Theme_Inheritance_Template_Directories_Collected' => 'onCollectTemplateDirs',
            'Theme_Compiler_Collect_Plugin_Less'               => 'onCollectLessFiles',
            'Theme_Compiler_Collect_Plugin_Javascript'         => 'onCollectJavaScript',
        ];
    }

    public function onCollectTemplateDirs(EventArgs $args)
    {
        $dirs   = $args->getReturn();
        $dirs[] = $this->pluginDir . '/Resources/views';

        $args->setReturn($dirs);
    }

    public function onCollectLessFiles(): ArrayCollection
    {
        $less = new LessDefinition(
            [],
            // less files to compile
            [$this->pluginDir . '/Resources/views/frontend/_public/src/less/all.less'],
            // import directory
            $this->pluginDir
        );

        return new ArrayCollection([$less]);
    }

    public function onCollectJavaScript(): ArrayCollection
    {
        $jsPath = [
            $this->pluginDir . '/Resources/views/frontend/_public/src/js/jquery.heidelpay-base.js',
            $this->pluginDir . '/Resources/views/frontend/_public/src/js/jquery.heidelpay-credit-card.js',
            $this->pluginDir . '/Resources/views/frontend/_public/src/js/jquery.heidelpay-ideal.js',
            $this->pluginDir . '/Resources/views/frontend/_public/src/js/jquery.heidelpay-eps.js',
            $this->pluginDir . '/Resources/views/frontend/_public/src/js/jquery.heidelpay-sepa-direct-debit.js',
            $this->pluginDir . '/Resources/views/frontend/_public/src/js/jquery.heidelpay-sepa-direct-debit-guaranteed.js',
            $this->pluginDir . '/Resources/views/frontend/_public/src/js/jquery.heidelpay-invoice-guaranteed.js',
            $this->pluginDir . '/Resources/views/frontend/_public/src/js/jquery.heidelpay-invoice-factoring.js',
        ];

        return new ArrayCollection($jsPath);
    }
}
