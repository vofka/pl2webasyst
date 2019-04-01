<?php

class pocketlistsUser
{
    /**
     * @var waContact
     */
    private $contact;

    /**
     * @var pocketlistsUserSettings
     */
    private $settings;

    /**
     * pocketlistsUser constructor.
     *
     * @param waContact $contact
     */
    public function __construct(waContact $contact)
    {
        $this->contact = $contact;
    }

    /**
     * @return pocketlistsUserSettings
     */
    public function getSettings()
    {
        if ($this->settings === null) {
            $this->settings = new pocketlistsUserSettings();
        }

        return $this->settings;
    }

    /**
     * @param pocketlistsUserSettings $settings
     *
     * @return pocketlistsUser
     */
    public function setSettings(pocketlistsUserSettings $settings)
    {
        $this->settings = $settings;

        return $this;
    }

    /**
     * @return waContact
     */
    public function getContact()
    {
        return $this->contact;
    }

    /**
     * @return int
     */
    public function hasLinkedApps()
    {
        /** @var pocketlistsItemLinkInterface[] $apps */
        $apps = wa(pocketlistsHelper::APP_ID)->getConfig()->getLinkedApp();

        if (!$apps) {
            return 0;
        }

        /** @var pocketlistsItemLinkInterface $app */
        foreach ($apps as $app) {
            if ($app->userCanAccess($this)) {
                return 1;
            }
        }

        return 0;
    }

    /**
     * @return int|null
     * @throws waException
     */
    public function getAppCount()
    {
        $icon = $this->getSettings()->appIcon();

        /** @var pocketlistsItemModel $itemModel */
        $itemModel = pl2()->getModel(pocketlistsItem::class);

        $count = 0;
        switch ($icon) {
            case pocketlistsUserSettings::ICON_OVERDUE: // overdue
                $items = $itemModel->fetchTodo(
                    $this->getContact()->getId(),
                    false,
                    [
                        pocketlistsItem::PRIORITY_RED,
                        pocketlistsItem::PRIORITY_BLACK,
                        pocketlistsItem::PRIORITY_BURNINHELL,
                    ]
                );
                $count = count($items);

                break;

            case pocketlistsUserSettings::ICON_OVERDUE_TODAY: // overdue + today
                $items = $itemModel->fetchTodo(
                    $this->getContact()->getId(),
                    false,
                    [
                        pocketlistsItem::PRIORITY_YELLOW,
                        pocketlistsItem::PRIORITY_RED,
                        pocketlistsItem::PRIORITY_BLACK,
                        pocketlistsItem::PRIORITY_BURNINHELL,
                    ]
                );
                $count = count($items);

                break;

            case pocketlistsUserSettings::ICON_OVERDUE_TODAY_AND_TOMORROW: // overdue + today + tomorrow
                $items = $itemModel->fetchTodo(
                    $this->getContact()->getId(),
                    false,
                    [
                        pocketlistsItem::PRIORITY_GREEN,
                        pocketlistsItem::PRIORITY_YELLOW,
                        pocketlistsItem::PRIORITY_RED,
                        pocketlistsItem::PRIORITY_BLACK,
                        pocketlistsItem::PRIORITY_BURNINHELL,
                    ]
                );
                $count = count($items);

                break;
        }

        return $count ?: null;
    }
}
