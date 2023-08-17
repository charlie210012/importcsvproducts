<?php
class AdminImportCsvProductsController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function initContent()
    {
        parent::initContent();
        $this->context->smarty->assign('module_dir', $this->module->getLocalPath());
        $this->setTemplate('module:'.$this->module->name.'/views/templates/admin/import.tpl');
    }
}
