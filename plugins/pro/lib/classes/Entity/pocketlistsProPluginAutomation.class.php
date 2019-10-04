<?php

/**
 * Class pocketlistsProPluginAutomation
 */
class pocketlistsProPluginAutomation extends pocketlistsEntity
{
    const GROUP_SHOP = 'shop';

    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $event;

    /**
     * @var pocketlistsProPluginAutomationRuleInterface[]|string
     */
    private $rules;

    /**
     * @var pocketlistsProPluginAutomationActionInterface|string
     */
    private $action;

    /**
     * @var string
     */
    private $group = self::GROUP_SHOP;

    /**
     * @var int
     */
    private $created_by;

    /**
     * @var DateTime|string
     */
    private $created_datetime;

    /**
     * @var DateTime|string
     */
    private $updated_datetime;

    /**
     * @var string
     */
    private $rulesJson;

    /**
     * @var
     */
    private $actionJson;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     *
     * @return pocketlistsProPluginAutomation
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return string
     */
    public function getEvent()
    {
        return $this->event;
    }

    /**
     * @param string $event
     *
     * @return pocketlistsProPluginAutomation
     */
    public function setEvent($event)
    {
        $this->event = $event;

        return $this;
    }

    /**
     * @return pocketlistsProPluginAutomationRuleInterface[]|string
     */
    public function getRules()
    {
        return $this->rules;
    }

    /**
     * @param pocketlistsProPluginAutomationRuleInterface[]|string $rules
     *
     * @return pocketlistsProPluginAutomation
     */
    public function setRules($rules)
    {
        $this->rules = $rules;

        return $this;
    }

    /**
     * @return pocketlistsProPluginAutomationActionInterface|string
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * @param pocketlistsProPluginAutomationActionInterface|string $action
     *
     * @return pocketlistsProPluginAutomation
     */
    public function setAction($action)
    {
        $this->action = $action;

        return $this;
    }

    /**
     * @return string
     */
    public function getGroup()
    {
        return $this->group;
    }

    /**
     * @param string $group
     *
     * @return pocketlistsProPluginAutomation
     */
    public function setGroup($group)
    {
        $this->group = $group;

        return $this;
    }

    /**
     * @return int
     */
    public function getCreatedBy()
    {
        return $this->created_by;
    }

    /**
     * @param int $created_by
     *
     * @return pocketlistsProPluginAutomation
     */
    public function setCreatedBy($created_by)
    {
        $this->created_by = $created_by;

        return $this;
    }

    /**
     * @return DateTime|string
     */
    public function getCreatedDatetime()
    {
        return $this->created_datetime;
    }

    /**
     * @param DateTime|string $created_datetime
     *
     * @return pocketlistsProPluginAutomation
     */
    public function setCreatedDatetime($created_datetime)
    {
        $this->created_datetime = $created_datetime;

        return $this;
    }

    /**
     * @return DateTime|string
     */
    public function getUpdatedDatetime()
    {
        return $this->updated_datetime;
    }

    /**
     * @param DateTime|string $updated_datetime
     *
     * @return pocketlistsProPluginAutomation
     */
    public function setUpdatedDatetime($updated_datetime)
    {
        $this->updated_datetime = $updated_datetime;

        return $this;
    }

    public function afterExtract(array &$fields)
    {
        $rules = $this->rules;
        $this->rules = $this->rulesJson;
        $this->rulesJson = $rules;

        $action = $this->action;
        $this->action = $this->actionJson;
        $this->actionJson = $action;
    }

    public function afterHydrate($data = [])
    {
        if ($this->rules) {
            $rules = [];

            foreach ($this->rules as $rule) {
                if (!empty($rule['identifier'])) {
                    $rules[] = pocketlistsProPlugin::getInstance()->getAutomationService()->createRule(
                        $rule['identifier'],
                        $rule
                    );
                }
            }
            $this->rules = $rules;
            $this->rulesJson = json_encode($this->rules, JSON_UNESCAPED_UNICODE);
        }

        if ($this->action) {
            $this->action = (new pocketlistsProPluginCreateItemAction())->load($this->action[pocketlistsProPluginCreateItemAction::IDENTIFIER]);
            $this->actionJson = json_encode($this->action, JSON_UNESCAPED_UNICODE);
        }
    }

    public function beforeExtract(array &$fields)
    {
        $rules = $this->rules;
        $this->rules = $this->rulesJson;
        $this->rulesJson = $rules;

        $action = $this->action;
        $this->action = $this->actionJson;
        $this->actionJson = $action;
    }
}
