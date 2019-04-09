<?php

/**
 * Class pocketlistsShopBackend_orderHandler
 */
class pocketlistsShopBackend_orderHandler extends waEventHandler
{
    /**
     * @param $params
     *
     * @return array
     * @throws waException
     */
    public function execute(&$params)
    {
        /** @var waSmarty3View $view */
        $view = new waSmarty3View(wa());

        $return = [];

        $app = pl2()->getLinkedApp('shop');

        if (!$app->isEnabled()) {
            return $return;
        }

        if (!$app->userCanAccess()) {
            return $return;
        }

        $hasItems = pl2()->getModel(pocketlistsItemLink::class)->countLinkedItems('shop', 'order', $params['id']);

        $viewParams = [
            'wa_app_static_url' => wa()->getAppStaticUrl(pocketlistsHelper::APP_ID),
            'app'               => $app,
            'order'             => $params,
            'plurl'             => wa()->getAppUrl(pocketlistsHelper::APP_ID),
            'items_undone'      => [],
            'items_done'        => [],
            'count_done_items'  => 0,
            'fileupload'        => 1,
            'user'              => pl2()->getUser(),
        ];

        if ($hasItems) {
            $items = pl2()
                ->getEntityFactory(pocketlistsItem::class)
                ->findAllForApp($app, 'order', $params['id']);

            $filter = (new pocketlistsStrategyItemFilterAndSort($items))->filterDoneUndone();

            if ($items) {
                $viewParams['items_undone'] = $filter->getProperSortUndone()->getItemsUndone();
                $viewParams['items_done'] = $filter->getItemsDone();
                $viewParams['count_done_items'] = $filter->countDone();
            }
        }

        foreach (['aux_info', 'action_link', 'info_section', 'title_suffix', 'action_button'] as $hook) {
            $template = wa()->getAppPath(
                sprintf('templates/include/app_hook/shop.backend_order.%s.html', $hook),
                pocketlistsHelper::APP_ID
            );

            if (file_exists($template)) {
                $view->assign('params', $viewParams);
                $view->assign('pl2_attachments_path', wa()->getDataUrl('attachments', true, pocketlistsHelper::APP_ID));
                $return[$hook] = $view->fetch($template);
            }
        }

        return $return;
    }
}
