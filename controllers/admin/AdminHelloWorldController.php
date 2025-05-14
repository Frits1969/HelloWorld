<?php
class AdminHelloWorldController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->bootstrap = true;
    }

    public function initContent()
    {
        parent::initContent();

        $helloText = $this->trans('Hello World', [], 'Modules.Helloworld.Admin');
        $this->context->smarty->assign([
            'helloText' => $helloText
        ]);

        $this->setTemplate('helloworld.tpl');
    }
}
