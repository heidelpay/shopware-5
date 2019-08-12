<?php

namespace HeidelPayment\Services\ViewBehaviorHandler;

use Enlight_View_Default as View;
use Smarty_Data;

interface ViewBehaviorHandlerInterface
{
    public function processCheckoutFinishBehavior(View $view, string $paymentId);

    /**
     * @see `s_core_documents`.`id` $documentType
     */
    public function processDocumentBehavior(Smarty_Data $viewAssignments, string $paymentId, int $documentType);

    public function processEmailVariablesBehavior(string $paymentId): array;
}