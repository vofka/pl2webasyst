<?php

/**
 * Class pocketlistsArchiveAction
 */
class pocketlistsArchiveAction extends pocketlistsViewAction
{
    /**
     * @throws waException
     */
    public function execute()
    {
        /** @var pocketlistsListFactory $listFactory */
        $listFactory = pl2()->getEntityFactory(pocketlistsList::class);

        /** @var pocketlistsList[] $lists */
        $lists = $listFactory->findLists();
        $lists = (new pocketlistsStrategyListFilter())->filterArchive($lists, true);

        $list_id = waRequest::get('id', 0, waRequest::TYPE_INT);
        $list = null;

        if ($lists) {
            $list = reset($lists);
        }

        if ($list_id) {
            /** @var pocketlistsListModel $list */
            $list = $listFactory->findById($list_id);
        }

        if ($list && $list->isArchived()) {
            if (!pocketlistsRBAC::canAccessToList($list)) {
                $this->view->assign(
                    'error',
                    [
                        'code'    => 403,
                        'message' => _w('Access denied'),
                    ]
                );
                $this->setTemplate('templates/include/error.html');

                return;
            }

            /** @var pocketlistsContactFactory $factory */
            $factory = wa(pocketlistsHelper::APP_ID)->getConfig()->getEntityFactory(pocketlistsContact::class);
            $list_access_contacts = $factory->getTeammates(
                pocketlistsRBAC::getAccessContacts($list),
                true,
                false
            );

            /** @var pocketlistsItemModel $itemModel */
            $itemModel = pl2()->getModel(pocketlistsItem::class);

            $count_undone = $itemModel->countByField(
                [
                    'list_id' => $list_id,
                    'status'  => 0,
                ]
            );
            $count_done = $itemModel->countByField(
                [
                    'list_id' => $list_id,
                    'status'  => 1,
                ]
            );

            $undone = $list->getUndoneItems();
            $done = $list->getDoneItems();

            $this->view->assign(
                [
                    'items'                => $undone,
                    'empty'                => count($undone),
                    'items_done'           => $done,
                    'count_items_done'     => $count_done,
                    'count_items_undone'   => $count_undone,
                    'new'                  => false,
                    'pl2_attachments_path' =>
                        wa()->getDataUrl('attachments/', true, pocketlistsHelper::APP_ID),
                    'list_access_contacts' => $list_access_contacts,
                    'list'                 => $list,
                    'lists'                => $lists,
                    'user'                 => $this->user,
                ]
            );
        }
    }
}
