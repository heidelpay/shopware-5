<?php

declare(strict_types=1);

namespace HeidelPayment\Subscribers\Frontend;

use Enlight\Event\SubscriberInterface;
use Enlight_Controller_ActionEventArgs as ActionEventArgs;
use HeidelPayment\Services\DependencyProviderServiceInterface;
use HeidelPayment\Services\PaymentIdentificationServiceInterface;
use HeidelPayment\Services\PaymentVault\PaymentVaultServiceInterface;
use HeidelPayment\Services\ViewBehaviorFactoryInterface;
use HeidelPayment\Services\ViewBehaviorHandler\ViewBehaviorHandlerInterface;
use Shopware\Bundle\StoreFrontBundle\Service\ContextServiceInterface;

class Checkout implements SubscriberInterface
{
    /** @var ContextServiceInterface */
    private $contextService;

    /** @var PaymentIdentificationServiceInterface */
    private $paymentIdentificationService;

    /** @var DependencyProviderServiceInterface */
    private $dependencyProvider;

    /** @var ViewBehaviorFactoryInterface */
    private $viewBehaviorFactory;

    /** @var PaymentVaultServiceInterface */
    private $paymentVaultService;

    /** @var string */
    private $pluginDir;

    public function __construct(
        ContextServiceInterface $contextService,
        PaymentIdentificationServiceInterface $paymentIdentificationService,
        DependencyProviderServiceInterface $dependencyProvider,
        ViewBehaviorFactoryInterface $viewBehaviorFactory,
        PaymentVaultServiceInterface $paymentVaultService,
        string $pluginDir
    ) {
        $this->contextService               = $contextService;
        $this->paymentIdentificationService = $paymentIdentificationService;
        $this->paymentVaultService          = $paymentVaultService;
        $this->dependencyProvider           = $dependencyProvider;
        $this->viewBehaviorFactory          = $viewBehaviorFactory;
        $this->pluginDir                    = $pluginDir;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'Enlight_controller_action_PostDispatchSecure_Frontend_Checkout' => [
                ['onPostDispatchConfirm'],
                ['onPostDispatchFinish'],
                ['onPostDispatchShippingPayment'],
            ],
        ];
    }

    public function onPostDispatchConfirm(ActionEventArgs $args): void
    {
        $request = $args->getRequest();

        if ($request->getActionName() !== 'confirm') {
            return;
        }

        $view                  = $args->getSubject()->View();
        $selectedPaymentMethod = $this->getSelectedPayment();

        if (!$selectedPaymentMethod) {
            return;
        }

        $userData       = $view->getAssign('sUserData');
        $vaultedDevices = $this->paymentVaultService->getVaultedDevicesForCurrentUser($userData['billingaddress'], $userData['shippingaddress']);
        $locale         = str_replace('_', '-', $this->contextService->getShopContext()->getShop()->getLocale()->getLocale());
        $hasFrame       = $this->paymentIdentificationService->isHeidelpayPaymentWithFrame($selectedPaymentMethod);

        $view->assign('hasHeidelpayFrame', $hasFrame);
        $view->assign('heidelpayVault', $vaultedDevices);
        $view->assign('heidelpayLocale', $locale);
    }

    public function onPostDispatchFinish(ActionEventArgs $args): void
    {
        $request = $args->getRequest();

        if ($request->getActionName() !== 'finish') {
            return;
        }

        $session         = $this->dependencyProvider->getSession();
        $selectedPayment = $this->getSelectedPayment();

        if (empty($selectedPayment)) {
            return;
        }

        $selectedPaymentName = $selectedPayment['name'];

        if (!$session->offsetExists('heidelPaymentId') ||
            !$this->paymentIdentificationService->isHeidelpayPayment($selectedPayment)
        ) {
            return;
        }

        $view            = $args->getSubject()->View();
        $heidelPaymentId = $session->offsetGet('heidelPaymentId');

        $viewHandlers         = $this->viewBehaviorFactory->getBehaviorHandler($selectedPayment['name']);
        $behaviorTemplatePath = sprintf('%s/Resources/views/frontend/heidelpay/behaviors/%s/finish.tpl', $this->pluginDir, $selectedPaymentName);
        $behaviorTemplate     = sprintf('frontend/heidelpay/behaviors/%s/finish.tpl', $selectedPaymentName);

        /** @var ViewBehaviorHandlerInterface $behavior */
        foreach ($viewHandlers as $behavior) {
            $behavior->processCheckoutFinishBehavior($view, $heidelPaymentId);
        }

        if (file_exists($behaviorTemplatePath)) {
            $view->loadTemplate($behaviorTemplate);
        }

        $session->offsetUnset('heidelPaymentId');
    }

    public function onPostDispatchShippingPayment(ActionEventArgs $args): void
    {
        $request = $args->getRequest();

        if ($request->getActionName() !== 'shippingPayment') {
            return;
        }

        $heidelpayMessage = $request->get('heidelpayMessage', false);

        if (empty($heidelpayMessage) || $heidelpayMessage === false) {
            return;
        }

        $heidelpayMessage = base64_decode($heidelpayMessage);

        $view     = $args->getSubject()->View();
        $messages = (array) $view->getAssign('sErrorMessages');

        $messages[] = $heidelpayMessage;

        $view->assign('sErrorMessages', $messages);
    }

    private function getSelectedPayment(): array
    {
        return $this->dependencyProvider->getSession()->offsetGet('sOrderVariables')['sUserData']['additional']['payment'];
    }
}
