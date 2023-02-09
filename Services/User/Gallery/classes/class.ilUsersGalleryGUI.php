<?php

declare(strict_types=1);

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

/**
 * @ilCtrl_Calls ilUsersGalleryGUI: ilPublicUserProfileGUI
 * @ilCtrl_isCalledBy ilUsersGalleryGUI: ilCourseMembershipGUI, ilGroupMembershipGUI
 */
class ilUsersGalleryGUI
{
    protected ilUsersGalleryCollectionProvider $collection_provider;
    protected ilCtrl $ctrl;
    protected ilGlobalTemplateInterface $tpl;
    protected ilLanguage $lng;
    protected ilObjUser $user;
    protected ilRbacSystem $rbacsystem;
    protected \ILIAS\UI\Factory $factory;
    protected \ILIAS\UI\Renderer $renderer;
    protected \ILIAS\HTTP\GlobalHttpState $http;
    protected \ILIAS\Refinery\Factory $refinery;

    public function __construct(ilUsersGalleryCollectionProvider $collection_provider)
    {
        /** @var $DIC ILIAS\DI\Container */
        global $DIC;

        $this->ctrl = $DIC->ctrl();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->lng = $DIC->language();
        $this->user = $DIC->user();
        $this->rbacsystem = $DIC->rbac()->system();
        $this->factory = $DIC->ui()->factory();
        $this->renderer = $DIC->ui()->renderer();
        $this->http = $DIC->http();
        $this->refinery = $DIC->refinery();

        $this->collection_provider = $collection_provider;
    }

    public function executeCommand(): void
    {
        $next_class = $this->ctrl->getNextClass();
        $cmd = $this->ctrl->getCmd('view');

        switch (strtolower($next_class)) {
            case strtolower(ilPublicUserProfileGUI::class):
                $profile_gui = new ilPublicUserProfileGUI(
                    $this->http->wrapper()->query()->retrieve(
                        'user',
                        $this->refinery->kindlyTo()->int()
                    )
                );
                $profile_gui->setBackUrl($this->ctrl->getLinkTarget($this, 'view'));
                $this->ctrl->forwardCommand($profile_gui);
                break;

            default:
                switch ($cmd) {
                    default:
                        $this->$cmd();
                        break;
                }
                break;
        }
    }

    protected function view(): void
    {
        $template = $this->populateTemplate($this->collection_provider->getGroupedCollections());
        $this->tpl->setContent($template->get());
    }

    /**
     * @param ilObjUser $user
     * @param \ILIAS\UI\Component\Component[] $sections
     */
    protected function addActionSection(ilObjUser $user, array &$sections): void
    {
        $contact_btn_html = "";

        if (
            !$this->user->isAnonymous() &&
            !$user->isAnonymous() &&
            ilBuddySystem::getInstance()->isEnabled() &&
            $this->user->getId() !== $user->getId()
        ) {
            $contact_btn_html = ilBuddySystemLinkButton::getInstanceByUserId($user->getId())->getHtml();
        }

        $ua_gui = ilUserActionGUI::getInstance(new ilGalleryUserActionContext(), $this->tpl, $this->user->getId());
        $list_html = $ua_gui->renderDropDown($user->getId());

        if ($contact_btn_html || $list_html) {
            $sections[] = $this->factory->legacy(
                "<div style='float:left; margin-bottom:5px;'>" . $contact_btn_html . "</div><div class='button-container'>&nbsp;" . $list_html . "</div>"
            );
        }
    }

    /**
     * @param ilUsersGalleryUserCollection[] $gallery_groups
     * @return ilTemplate
     */
    protected function populateTemplate(array $gallery_groups): ilTemplate
    {
        $buddylist = ilBuddyList::getInstanceByGlobalUser();
        $tpl = new ilTemplate('tpl.users_gallery.html', true, true, 'Services/User');

        $panel = ilPanelGUI::getInstance();
        $panel->setBody($this->lng->txt('no_gallery_users_available'));
        $tpl->setVariable('NO_ENTRIES_HTML', json_encode($panel->getHTML(), JSON_THROW_ON_ERROR));

        $groups_with_users = array_filter($gallery_groups, static function (ilUsersGalleryGroup $group): bool {
            return count($group) > 0;
        });
        $groups_with_highlight = array_filter($groups_with_users, static function (ilUsersGalleryGroup $group): bool {
            return $group->isHighlighted();
        });

        if (0 === count($groups_with_users)) {
            $tpl->setVariable('NO_GALLERY_USERS', $panel->getHTML());
            return $tpl;
        }

        $panel = ilPanelGUI::getInstance();
        $panel->setBody($this->lng->txt('no_gallery_users_available'));
        $tpl->setVariable('NO_ENTRIES_HTML', json_encode($panel->getHTML(), JSON_THROW_ON_ERROR));

        $cards = [];

        // BEGIN PATCH HSLU: Hide people in hidden admin role
        try {
            if (class_exists("EventoImport\\import\\data_management\\repository\\HiddenAdminRepository", true)) {
                global $DIC;
                $rbac_review = $DIC->rbac()->review();
                $query_params = $DIC->http()->request()->getQueryParams();
                if (isset($query_params['ref_id'])) {
                    $ref_id = (int) $query_params['ref_id'];
                    $hidden_admin_repo = new \EventoImport\import\data_management\repository\HiddenAdminRepository($DIC->database());
                    $hidden_admin_role_id = $hidden_admin_repo->getRoleIdForContainerRefId($ref_id);
                } else {
                    $hidden_admin_role_id = null;
                }
            } else {
                $hidden_admin_role_id = null;
            }
        } catch (Exception $e) {
            // If any errors happen in this patch -> skip it. This is only a feature for convenience
            $hidden_admin_role_id = null;
        }
        // END PATCH HSLU: Hide people in hidden admin role

        foreach ($gallery_groups as $group) {
            $group = new ilUsersGallerySortedUserGroup($group, new ilUsersGalleryUserCollectionPublicNameSorter());

            foreach ($group as $user) {
                // BEGIN PATCH HSLU: Hide people in hidden admin role
                try {
                    if (!is_null($hidden_admin_role_id) && !is_null($rbac_review) && $rbac_review->isAssigned($user->getAggregatedUser()->getId(), $hidden_admin_role_id)) {
                        continue;
                    }
                } catch (Exception $e) {
                    // If any errors happen in this patch -> skip it. This is only a feature for convenience
                }
                // END PATCH HSLU: Hide people in hidden admin role
                $card = $this->factory->card()->standard($user->getPublicName());
                $avatar = $this->factory->image()->standard(
                    $user->getAggregatedUser()->getPersonalPicturePath('big'),
                    $user->getPublicName()
                );

                $sections = [];

                if (count($groups_with_highlight) > 0) {
                    $card = $card->withHighlight($group->isHighlighted());
                }

                $sections[] = $this->factory->listing()->descriptive(
                    [
                        $this->lng->txt("username") => $user->getAggregatedUser()->getLogin(),
                        $this->lng->txt("crs_contact_responsibility") => $group->getLabel()
                    ]
                );

                $this->addActionSection($user->getAggregatedUser(), $sections);

                if ($user->hasPublicProfile()) {
                    $this->ctrl->setParameterByClass(
                        ilPublicUserProfileGUI::class,
                        'user',
                        $user->getAggregatedUser()->getId()
                    );
                    $public_profile_url = $this->ctrl->getLinkTargetByClass(ilPublicUserProfileGUI::class, 'getHTML');

                    $avatar = $avatar->withAction($public_profile_url);
                    $card = $card->withTitleAction($public_profile_url);
                }

                $card = $card->withImage($avatar)->withSections($sections);

                $cards[] = $card;
            }
        }

        $tpl->setVariable('GALLERY_HTML', $this->renderer->render($this->factory->deck($cards)));

        if ($this->collection_provider->hasRemovableUsers()) {
            $tpl->touchBlock('js_remove_handler');
        }

        return $tpl;
    }
}
