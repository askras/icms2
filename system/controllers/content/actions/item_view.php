<?php

class actionContentItemView extends cmsAction {

    public function run(){

        $props = $props_values = false;

        // Получаем название типа контента и сам тип
        $ctype = $this->model->getContentTypeByName($this->request->get('ctype_name', ''));

		// Получаем SLUG записи
        $slug = $this->request->get('slug', '');

		if (!$ctype) {
			if ($this->cms_config->ctype_default){
				$ctype = $this->model->getContentTypeByName($this->cms_config->ctype_default);
				if (!$ctype) { cmsCore::error404(); }
				$slug = $ctype['name'] . '/' . $slug;
			} else {
				cmsCore::error404();
			}
		} else {
			if ($this->cms_config->ctype_default && $this->cms_config->ctype_default == $this->cms_core->uri_action){
				$this->redirect(href_to($slug . '.html'), 301);
			}
            // если название переопределено, то редиректим со старого на новый
            $mapping = cmsConfig::getControllersMapping();
            if($mapping){
                foreach($mapping as $name=>$alias){
                    if ($name == $ctype['name'] && !$this->cms_core->uri_controller_before_remap) {
                        $this->redirect(href_to($alias.'/'. $slug.'.html'), 301);
                    }
                }
            }
		}

        // чтобы привязки виджетов к записям работали
        if ($this->cms_config->ctype_default && $this->cms_config->ctype_default == $ctype['name']){
            $this->cms_core->uri = $this->cms_config->ctype_default .'/'. $this->cms_core->uri;
        }

		if (!$ctype['options']['item_on']) { cmsCore::error404(); }

        // Получаем запись
        $item = $this->model->getContentItemBySLUG($ctype['name'], $slug);
        if (!$item) { cmsCore::error404(); }

        // Проверяем прохождение модерации
        $is_moderator = $this->cms_user->is_admin || $this->model->userIsContentTypeModerator($ctype['name'], $this->cms_user->id);
        if (!$item['is_approved']){
            if (!$is_moderator && $this->cms_user->id != $item['user_id']){ cmsCore::error404(); }
        }

        // Проверяем публикацию
        if (!$item['is_pub']){
            if (!$is_moderator && $this->cms_user->id != $item['user_id']){ cmsCore::error404(); }
        }

        // Проверяем приватность
        if ($item['is_private'] == 1){ // доступ только друзьям

            $is_friend           = $this->cms_user->isFriend($item['user_id']);
            $is_can_view_private = cmsUser::isAllowed($ctype['name'], 'view_all');

            if (!$is_friend && !$is_can_view_private && !$is_moderator){
                // если в настройках указано скрывать, 404
                if(empty($ctype['options']['privacy_type']) || $ctype['options']['privacy_type'] == 'hide'){
                    cmsCore::error404();
                }
                // иначе пишем, к кому в друзья нужно проситься
                cmsUser::addSessionMessage(sprintf(
                    LANG_CONTENT_PRIVATE_FRIEND_INFO,
                    (!empty($ctype['labels']['one']) ? $ctype['labels']['one'] : LANG_PAGE),
                    href_to('users', $item['user_id']),
                    htmlspecialchars($item['user']['nickname'])
                ), 'info');
                $this->redirect(href_to($ctype['name']));
            }

        }

        // Проверяем ограничения доступа из других контроллеров
        if ($item['is_parent_hidden']){
            $is_parent_viewable_result = cmsEventsManager::hook('content_view_hidden', array(
                'viewable'     => true,
                'item'         => $item,
                'is_moderator' => $is_moderator
            ));
            if (!$is_parent_viewable_result['viewable']){

                if(isset($is_parent_viewable_result['access_text'])){

                    cmsUser::addSessionMessage($is_parent_viewable_result['access_text'], 'error');

                    if(isset($is_parent_viewable_result['access_redirect_url'])){
                        $this->redirect($is_parent_viewable_result['access_redirect_url']);
                    } else {
                        $this->redirect(href_to($ctype['name']));
                    }

                }

                cmsUser::goLogin();
            }
        }

        $item['ctype_name'] = $ctype['name'];

        if ($ctype['is_cats'] && $item['category_id'] > 1){
            $item['category'] = $this->model->getCategory($ctype['name'], $item['category_id']);
        }

        $childs = array(
            'relations' => $this->model->getContentTypeChilds($ctype['id']),
            'to_add'    => array(),
            'to_bind'   => array(),
            'tabs'      => array(),
            'items'     => array()
        );

        if ($childs['relations']){

            $model = cmsCore::getModel('content');

            foreach($childs['relations'] as $relation){

                $model->resetFilters();

                $perm = cmsUser::getPermissionValue($relation['child_ctype_name'], 'add_to_parent');
                $is_allowed_to_add = $perm && ($perm == 'to_all' || ($perm == 'to_own' && $item['user_id'] == $this->cms_user->id));

                $perm = cmsUser::getPermissionValue($relation['child_ctype_name'], 'bind_to_parent');
                $is_allowed_to_bind = $perm && (
                                        ($perm == 'all_to_all' || $perm == 'own_to_all' || $perm == 'other_to_all') ||
                                        (($perm == 'all_to_own' || $perm == 'own_to_own' || $perm == 'other_to_own') && $item['user_id'] == $this->cms_user->id) ||
                                        (($perm == 'all_to_other' || $perm == 'own_to_other' || $perm == 'other_to_other') && $item['user_id'] != $this->cms_user->id)
                                    );

                if ($is_allowed_to_add) {
                    $childs['to_add'][] = $relation;
                }
                if ($is_allowed_to_bind) {
                    $childs['to_bind'][] = $relation;
                }

                $filter =   "r.parent_ctype_id = {$ctype['id']} AND ".
                            "r.parent_item_id = {$item['id']} AND ".
                            "r.child_ctype_id = {$relation['child_ctype_id']} AND ".
                            "r.child_item_id = i.id";

                $model->join('content_relations_bind', 'r', $filter);

                $count = $model->getContentItemsCount($relation['child_ctype_name']);

                $is_hide_empty = $relation['options']['is_hide_empty'];

                if (($count || !$is_hide_empty) && $relation['layout'] == 'tab'){

                    $childs['tabs'][] = array(
                        'title'   => $relation['title'],
                        'url'     => href_to($ctype['name'], $item['slug'], "view-{$relation['child_ctype_name']}"),
                        'counter' => $count
                    );

                }

                if (($count || !$is_hide_empty) && $relation['layout'] == 'list'){

                    $child_ctype = $model->getContentTypeByName($relation['child_ctype_name']);

                    if (!empty($relation['options']['limit'])){
                        $child_ctype['options']['limit'] = $relation['options']['limit'];
                    }

                    if (!empty($relation['options']['is_hide_filter'])){
                        $child_ctype['options']['list_show_filter'] = false;
                    }

                    $filter =   "r.parent_ctype_id = {$ctype['id']} AND ".
                                "r.parent_item_id = {$item['id']} AND ".
                                "r.child_ctype_id = {$child_ctype['id']} AND ".
                                "r.child_item_id = i.id";

                    $this->model->filterNotNull('r.id');
                    $this->model->join('content_relations_bind', 'r', $filter);

                    $childs['lists'][] = array(
                        'title' => empty($relation['options']['is_hide_title']) ? $relation['title'] : false,
                        'ctype_name' => $relation['child_ctype_name'],
                        'html' => $this->renderItemsList($child_ctype, href_to($ctype['name'], $item['slug'] . '.html'))
                    );

                }

            }

            list($ctype, $childs) = cmsEventsManager::hook('content_before_childs', array($ctype, $childs));

        }

        if ($this->request->has('child_ctype_name')){
            $child_ctype_name = $this->request->get('child_ctype_name', '');
            return $this->runAction('item_childs_view', array(
                'ctype'            => $ctype,
                'item'             => $item,
                'child_ctype_name' => $child_ctype_name,
                'childs'           => $childs
            ));
        }

        // Получаем поля для данного типа контента
        $fields = $this->model->getContentFields($ctype['name']);

        // Парсим значения полей
        foreach($fields as $name=>$field){
            $fields[ $name ]['html'] = $field['handler']->setItem($item)->parse( $item[$name] );
        }

        // Получаем поля-свойства
        if ($ctype['is_cats'] && $item['category_id'] > 1){
            $props = $this->model->getContentProps($ctype['name'], $item['category_id']);
            $props_values = $this->model->getPropsValues($ctype['name'], $item['id']);
        }

        // Рейтинг
        if ($ctype['is_rating'] &&  $this->isControllerEnabled('rating')){

            $rating_controller = cmsCore::getController('rating', new cmsRequest(array(
                'target_controller' => $this->name,
                'target_subject' => $ctype['name']
            ), cmsRequest::CTX_INTERNAL));

            $is_rating_allowed = cmsUser::isAllowed($ctype['name'], 'rate') && ($item['user_id'] != $this->cms_user->id);

            $item['rating_widget'] = $rating_controller->getWidget($item['id'], $item['rating'], $is_rating_allowed);

        }

        // Комментарии
        if ($ctype['is_comments'] && $item['is_approved'] && $item['is_comments_on'] &&  $this->isControllerEnabled('comments')){

            $comments_controller = cmsCore::getController('comments', new cmsRequest(array(
                'target_controller' => $this->name,
                'target_subject' => $ctype['name'],
                'target_user_id' => $item['user_id'],
                'target_id' => $item['id']
            ), cmsRequest::CTX_INTERNAL));

            $item['comments_widget'] = $comments_controller->getWidget();

        }

        // Получаем теги
        if ($ctype['is_tags']){
            $tags_model = cmsCore::getModel('tags');
            $item['tags'] = $tags_model->getTagsForTarget($this->name, $ctype['name'], $item['id']);
        }

        // Информация о модераторе для админа и владельца записи
        if ($item['approved_by'] && ($this->cms_user->is_admin || $this->cms_user->id == $item['user_id'])){
            $item['approved_by'] = cmsCore::getModel('users')->getUser($item['approved_by']);
        }

        list($ctype, $item, $fields) = cmsEventsManager::hook('content_before_item', array($ctype, $item, $fields));
        list($ctype, $item, $fields) = cmsEventsManager::hook("content_{$ctype['name']}_before_item", array($ctype, $item, $fields));

		if (!empty($ctype['options']['hits_on']) && $this->cms_user->id != $item['user_id'] && !$this->cms_user->is_admin){
			$this->model->incrementHitsCounter($ctype['name'], $item['id']);
		}

        // кешируем запись для получения ее в виджетах
        cmsModel::cacheResult('current_ctype', $ctype);
        cmsModel::cacheResult('current_ctype_item', $item);
        cmsModel::cacheResult('current_ctype_fields', $fields);
        cmsModel::cacheResult('current_ctype_props', $props);

        return $this->cms_template->render('item_view', array(
            'ctype'        => $ctype,
            'fields'       => $fields,
            'props'        => $props,
            'props_values' => $props_values,
            'item'         => $item,
            'is_moderator' => $is_moderator,
            'user'         => $this->cms_user,
            'childs'       => $childs,
        ));

    }

}
