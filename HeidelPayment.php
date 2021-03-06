<?php

declare(strict_types=1);

namespace HeidelPayment;

use HeidelPayment\Components\DependencyInjection\CompilerPass\PaymentStatusMapperCompilerPass;
use HeidelPayment\Components\DependencyInjection\CompilerPass\ViewBehaviorCompilerPass;
use HeidelPayment\Components\DependencyInjection\CompilerPass\WebhookCompilerPass;
use HeidelPayment\Installers\Attributes;
use HeidelPayment\Installers\Database;
use HeidelPayment\Installers\Document;
use HeidelPayment\Installers\PaymentMethods;
use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\ActivateContext;
use Shopware\Components\Plugin\Context\DeactivateContext;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UninstallContext;
use Shopware\Components\Plugin\Context\UpdateContext;
use Symfony\Component\DependencyInjection\ContainerBuilder;

//Load the heidelpay-php SDK
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

class HeidelPayment extends Plugin
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new WebhookCompilerPass());
        $container->addCompilerPass(new ViewBehaviorCompilerPass());
        $container->addCompilerPass(new PaymentStatusMapperCompilerPass());

        parent::build($container);
    }

    /**
     * {@inheritdoc}
     */
    public function install(InstallContext $context): void
    {
        $this->applyUpdates(null, $context->getCurrentVersion());

        parent::install($context);
    }

    /**
     * {@inheritdoc}
     */
    public function uninstall(UninstallContext $context): void
    {
        $snippetNamespace = $this->container->get('snippets')->getNamespace('backend/heidel_payment/pluginmanager');

        if (!$context->keepUserData()) {
            (new Database($this->container->get('dbal_connection')))->uninstall();
            (new Document($this->container->get('dbal_connection'), $this->container->get('translation')))->uninstall();
            (new Attributes($this->container->get('shopware_attribute.crud_service'), $this->container->get('models')))->uninstall();
        }

        (new PaymentMethods($this->container->get('models'), $this->container->get('shopware_attribute.data_persister')))->uninstall();

        $context->scheduleClearCache(InstallContext::CACHE_LIST_ALL);
        $context->scheduleMessage($snippetNamespace->get('uninstall/message'));
    }

    /**
     * {@inheritdoc}
     */
    public function update(UpdateContext $context): void
    {
        $snippetNamespace = $this->container->get('snippets')->getNamespace('backend/heidel_payment/pluginmanager');

        $this->applyUpdates($context->getCurrentVersion(), $context->getUpdateVersion());

        $context->scheduleMessage($snippetNamespace->get('update/message'));

        parent::update($context);
    }

    public function activate(ActivateContext $context): void
    {
        $snippetNamespace = $this->container->get('snippets')->getNamespace('backend/heidel_payment/pluginmanager');

        $context->scheduleClearCache(InstallContext::CACHE_LIST_ALL);
        $context->scheduleMessage($snippetNamespace->get('activate/message'));
    }

    public function deactivate(DeactivateContext $context): void
    {
        $context->scheduleClearCache(InstallContext::CACHE_LIST_ALL);
    }

    public function getVersion(): string
    {
        return $this->container->get('dbal_connection')->createQueryBuilder()
            ->select('version')
            ->from('s_core_plugins')
            ->where('name = :name')
            ->setParameter('name', $this->getName())
            ->execute()->fetchColumn();
    }

    private function applyUpdates(?string $oldVersion = null, ?string $newVersion = null): bool
    {
        $versionClosures = [
            '1.0.0' => function () {
                $modelManager = $this->container->get('models');
                $connection = $this->container->get('dbal_connection');
                $crudService = $this->container->get('shopware_attribute.crud_service');
                $translation = $this->container->get('translation');
                $dataPersister = $this->container->get('shopware_attribute.data_persister');

                (new Document($connection, $translation))->install();
                (new Database($connection))->install();
                (new Attributes($crudService, $modelManager))->install();
                (new PaymentMethods($modelManager, $dataPersister))->install();

                return true;
            },
            '1.0.2' => function () use ($oldVersion, $newVersion): void {
                $modelManager = $this->container->get('models');
                $dataPersister = $this->container->get('shopware_attribute.data_persister');

                (new PaymentMethods($modelManager, $dataPersister))->update($oldVersion ?? '', $newVersion ?? '');
            },
            '1.2.0' => function () use ($oldVersion, $newVersion): void {
                $modelManager = $this->container->get('models');
                $crudService = $this->container->get('shopware_attribute.crud_service');
                $dataPersister = $this->container->get('shopware_attribute.data_persister');

                (new Attributes($crudService, $modelManager))->install();
                (new PaymentMethods($modelManager, $dataPersister))->update($oldVersion ?? '', $newVersion ?? '');
            },
        ];

        foreach ($versionClosures as $version => $versionClosure) {
            if ($oldVersion === null || (version_compare($oldVersion, $version, '<') && version_compare($version, $newVersion, '<='))) {
                if (!$versionClosure($this)) {
                    return false;
                }
            }
        }

        return true;
    }
}
