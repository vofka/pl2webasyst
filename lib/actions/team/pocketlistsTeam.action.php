<?php

/**
 * Class pocketlistsTeamAction
 */
class pocketlistsTeamAction extends pocketlistsViewAction
{
    /**
     * @throws waException
     */
    public function execute()
    {
        // get all pocketlists users
        // all admin
        $teammates = [];
        $teammates_ids = pocketlistsRBAC::getAccessContacts();

        if ($teammates_ids) {
            /** @var pocketlistsContactFactory $factory */
            $factory = wa(pocketlistsHelper::APP_ID)->getConfig()->getEntityFactory(pocketlistsContact::class);
            $teammates = $factory->getTeammates($teammates_ids);

            $selected_teammate = waRequest::get('teammate');
            $lists = [];
            if ($selected_teammate) {
                $user_model = new waUserModel();
                $id = $user_model->getByLogin($selected_teammate);
                $teammate = new pocketlistsContact(new waContact($id['id']));

                /** @var pocketlistsListFactory $listFactory */
                $listFactory = pl2()->getEntityFactory(pocketlistsList::class);
                $lists = $listFactory->findForTeammate($teammate);

                $listFilter = new pocketlistsStrategyListFilterAndSort($lists);
                $lists = $listFilter->filter()->getNonArchived();

                /** @var pocketlistsList $list */
                foreach ($lists as $list_id => $list) {
                    $list->setLastContactAtivity($teammate->getListActivities($list));
                }

                $lists = $listFilter->sortUnarchivedByActivity();
            } else {
                $id = reset($teammates);
                $teammate = new pocketlistsContact(new waContact($id['id']));
            }

            /** @var pocketlistsItemFactory $itemFactory */
            $itemFactory = pl2()->getEntityFactory(pocketlistsItem::class);
            $items = $itemFactory->findAssignedOrCompletesByContact($teammate);

            $itemFilter = (new pocketlistsStrategyItemFilterAndSort($items))->filterDoneUndone();

            $this->view->assign(
                [
                    'lists'            => $lists,
                    'items'            => $itemFilter->properSortUndone()->getItemsUndone(),
                    'items_done'       => $itemFilter->getItemsDone(),
                    'count_done_items' => count($itemFilter->getItemsDone()),
                    'current_teammate' => $teammate,
                ]
            );
        }

        $this->view->assign(
            [
                'teammates'            => $teammates,
                'pl2_attachments_path' => wa()->getDataUrl('attachments/', true, pocketlistsHelper::APP_ID),
                'print'                => waRequest::get('print', false),
                'user'                 => $this->user,
            ]
        );
    }
}
