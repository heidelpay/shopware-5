<?php

declare(strict_types=1);

namespace HeidelPayment\Components\DependencyInjection\Factory\ViewBehavior;

use HeidelPayment\Components\ViewBehaviorHandler\ViewBehaviorHandlerInterface;

class ViewBehaviorFactory implements ViewBehaviorFactoryInterface
{
    /** @var ViewBehaviorHandlerInterface[][] */
    protected $viewBehaviorHandler;

    public function getBehaviorHandler(string $paymentName): array
    {
        if (!array_key_exists($paymentName, $this->viewBehaviorHandler)) {
            return [];
        }

        return $this->viewBehaviorHandler[$paymentName];
    }

    public function addBehaviorHandler(ViewBehaviorHandlerInterface $viewBehaviorHandler, string $paymentName): void
    {
        $this->viewBehaviorHandler[$paymentName][] = $viewBehaviorHandler;
    }
}
