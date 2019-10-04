<?php

/**
 * Class pocketlistsProPluginAutomationNewAction
 */
class pocketlistsProPluginAutomationNewAction extends pocketlistsViewAction
{
    /**
     * @throws pocketlistsForbiddenException
     * @throws pocketlistsLogicException
     * @throws waException
     */
    public function preExecute()
    {
        parent::preExecute();

        if (!pl2()->getLinkedApp('shop')->isEnabled()) {
            throw new pocketlistsLogicException(_wp('No Shop-Script installed'));
        }
    }

    /**
     * @param null|array $params
     *
     * @return mixed
     */
    public function runAction($params = null)
    {
        $actionId = waRequest::get('event', '', waRequest::TYPE_STRING_TRIM);

        $rules = [
            (new pocketlistsProPluginAutomationRuleShopAction())->load(['value' => $actionId]),
            new pocketlistsProPluginAutomationRuleShopPayment(),
            new pocketlistsProPluginAutomationRuleShopShipping(),
            new pocketlistsProPluginAutomationRuleShopAmount(),
            new pocketlistsProPluginAutomationRuleShopStorefront(),
            new pocketlistsProPluginAutomationRuleShopCustomerGroup(),
        ];

        $this->view->assign(
            [
                'rules' => $rules,
                'plAction' => new pocketlistsProPluginCreateItemAction()
            ]
        );
    }
}
