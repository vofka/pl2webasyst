<?php

/**
 * Class pocketlistsSettingsAction
 */
class pocketlistsSettingsAction extends pocketlistsViewAction
{
    /**
     * @throws waException
     */
    public function execute()
    {
        $settings = $this->user->getSettings()->getAllSettings();
        $this->view->assign('settings', $settings);

//        $inbox_list_id = $this->user->getSettings()->getStreamInboxList();
//        if ($inbox_list_id) {
//            /** @var pocketlistsListFactory $listFactory */
//            $listFactory = pl2()->getEntityFactory(pocketlistsList::class);
//            /** @var pocketlistsList $inbox_list */
//            $inbox_list = $listFactory->findById($inbox_list_id);
//
//            $this->view->assign(
//                [
//                    'inbox_lists' => $listFactory->findAllActive(),
//                    'inbox_list'  => $inbox_list,
//                ]
//            );
//        }

        $asp = new waAppSettingsModel();
        $this->view->assign(
            [
                'last_recap_cron_time' => $asp->get(wa()->getApp(), 'last_recap_cron_time'),
                'cron_command'         => pl2()->getCronJob('recap_mail'),
                'admin'                => pocketlistsRBAC::isAdmin(),
            ]
        );
    }
}
