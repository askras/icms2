<?php
    $this->addJS('templates/default/js/jquery-ui.js');
    $this->addJS('templates/default/js/jquery-cookie.js');
    $this->addJS('templates/default/js/datatree.js');
    $this->addCSS('templates/default/css/datatree.css');
    $this->addJS('templates/default/js/admin-content.js');
?>

<?php

    $this->setPageTitle(LANG_CP_SECTION_USERS);

    $this->addBreadcrumb(LANG_CP_SECTION_USERS, $this->href_to('users'));

    $this->addToolButton(array(
        'class' => 'filter',
        'title' => LANG_FILTER,
        'href'  => null,
        'onclick' => "return icms.modal.openAjax($(this).attr('href'))"
    ));

    $this->addToolButton(array(
        'class' => 'delete_filter important',
        'title' => LANG_CANCEL,
        'href'  => null,
        'onclick' => "return contentCancelFilter()"
    ));

    $this->addToolButton(array(
        'class' => 'add',
        'title' => LANG_CP_USER_ADD,
        'href'  => $this->href_to('users', 'add')
    ));

    $this->addToolButton(array(
        'class' => 'add_folder',
        'title' => LANG_CP_USER_GROUP_ADD,
        'href'  => $this->href_to('users', 'group_add')
    ));

    $this->addToolButton(array(
        'class' => 'edit',
        'title' => LANG_CP_USER_GROUP_EDIT,
        'href'  => $this->href_to('users', 'group_edit')
    ));

    $this->addToolButton(array(
        'class' => 'permissions',
        'title' => LANG_CP_USER_GROUP_PERMS,
        'href'  => $this->href_to('users', 'group_perms')
    ));

    $this->addToolButton(array(
        'class' => 'delete',
        'title' => LANG_CP_USER_GROUP_DELETE,
        'href'  => $this->href_to('users', 'group_delete'),
        'onclick' => "return confirm('".LANG_CP_USER_GROUP_DELETE_CONFIRM."')"
    ));

	$this->addToolButton(array(
		'class' => 'help',
		'title' => LANG_HELP,
		'target' => '_blank',
		'href'  => LANG_HELP_URL_USERS
	));

?>

<h1><?php echo LANG_CP_SECTION_USERS; ?></h1>

<table class="layout">
    <tr>
        <td class="sidebar" valign="top">

            <div id="datatree">
                <ul id="treeData" style="display: none">
                    <?php foreach($groups as $id=>$group){ ?>
                        <li id="<?php echo $group['id'];?>" class="folder"><?php echo $group['title']; ?></li>
                    <?php } ?>
                </ul>
            </div>

            <script type="text/javascript">
                    $(function(){

                        $('.cp_toolbar .delete_filter a').hide();

                        $("#datatree").dynatree({

                            onPostInit: function(isReloading, isError){
                                var path = $.cookie('icms[users_tree_path]');
                                if (!path) { path = '/0'; }
                                $("#datatree").dynatree("getTree").loadKeyPath(path, function(node, status){
                                    if(status == "loaded") {
                                        node.expand();
                                    }else if(status == "ok") {
                                        node.activate();
                                        node.expand();
                                        icms.datagrid.init();
                                    }
                                });
                            },

                            onActivate: function(node){
                                node.expand();
                                $.cookie('icms[users_tree_path]', node.getKeyPath(), {expires: 7, path: '/'});
                                var key = node.data.key;
                                icms.datagrid.setURL("<?php echo $this->href_to('users', array('ajax')); ?>/" + key);
                                $('.cp_toolbar .filter a').attr('href', "<?php echo $this->href_to('users', array('filter')); ?>/" + key[0]);
                                $('.cp_toolbar .add a').attr('href', "<?php echo $this->href_to('users', 'add'); ?>/" + key);
                                if (key == 0){
                                    $('.cp_toolbar .edit a').hide();
                                    $('.cp_toolbar .permissions a').hide();
                                    $('.cp_toolbar .delete a').hide();
                                } else {
                                    $('.cp_toolbar .edit a').show().attr('href', "<?php echo $this->href_to('users', 'group_edit'); ?>/" + key);
                                    $('.cp_toolbar .permissions a').show().attr('href', "<?php echo $this->href_to('users', 'group_perms'); ?>/" + key);
                                    $('.cp_toolbar .delete a').show().attr('href', "<?php echo $this->href_to('users', 'group_delete'); ?>/" + key);
                                }
                                icms.datagrid.loadRows();
                            }

                        });
                    });
            </script>

        </td>
        <td class="main" valign="top">

            <?php $this->renderGrid($this->href_to('users', array('ajax', 2)), $grid); ?>

        </td>
    </tr>
</table>

