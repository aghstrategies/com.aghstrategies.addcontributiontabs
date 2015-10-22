<?php

require_once 'addcontributiontabs.civix.php';

/**
 * Implementation of hook_civicrm_alterContent
 */
function addcontributiontabs_civicrm_alterContent(  &$content, $context, $tplName, &$object){
  if ( $context=='page' ) {
    if ( $tplName=='CRM/Contribute/Page/Tab.tpl' ) {
      if ( $object->_action == 16 ) {
        $marker1 = strpos($content, 'thead');
        $marker2 = strpos($content, '/form', $marker1);
        $marker = strpos($content, '</div', $marker2);

        $content1 = substr($content, 0, $marker);
        $content3 = substr($content, $marker);
        $content2 = '
          <table class="form-layout-compressed">
              <tr>
                  <th class="contriTotalLeft">'.ts('Related Contributions').'</th>
                  <th class="right" width="10px"> &nbsp; </th>
            <th class="right contriTotalRight"></th>
              </tr>
          </table>

          <table class="selector">
            <thead class="sticky">
              <tr>
                <th scope="col">'.ts('Related Contact').'</th>
                <th scope="col">'.ts('Relationship').'</th>
                <th scope="col">'.ts('Amount').'</th>
                <th scope="col">'.ts('Type').'</th>
                <th scope="col">'.ts('Source').'</th>
                <th scope="col">'.ts('Received').'</th>
                <th scope="col">'.ts('Thank-you Sent').'</th>
                <th scope="col">'.ts('Status').'</th>
                <th scope="col">'.ts('Premium').'</th><th scope="col"></th>
              </tr>
            </thead>';
        $contact_id = $object->getVar( '_contactId' );
        $related_contact_ids = array();
        $params = array(
          'version' => 3,
          'sequential' => 1,
          'contact_id_a' => $contact_id,
        );
        $relationships = civicrm_api( 'Relationship', 'get', $params );
        if ( $relationships['count'] > 0 ) {
          foreach ( $relationships['values'] as $relationship ) {
            if ( in_array($relationship['relationship_type_id'], array(5, 7, 8)) ) {
              $relationship_type = civicrm_api('RelationshipType', 'getSingle', array('version' => 3, 'id' => $relationship['relationship_type_id']));
              $related_contact_ids[] = array(
                'contact_id' => $relationship['contact_id_b'],
                'relationship_name' => $relationship_type['name_b_a'],
              );
            }
          }
        }
        $relationships = civicrm_api('Relationship', 'get', array('version' => 3, 'sequential' => 1, 'contact_id_b' => $contact_id, 'debug' => 1));
        if ( $relationships['count'] > 0 ) {
          foreach ( $relationships['values'] as $relationship ) {
            if ( in_array($relationship['relationship_type_id'], array(5, 7, 8)) ) {
              $relationship_type = civicrm_api('RelationshipType', 'getSingle', array('version' => 3, 'id' => $relationship['relationship_type_id']));
              $related_contact_ids[] = array(
                'contact_id' => $relationship['contact_id_a'],
                'relationship_name' => $relationship_type['name_a_b'],
              );
            }
          }
        }
        foreach ( $related_contact_ids as $related_contact ) {
          $contributions = civicrm_api('Contribution', 'get', array('version' => 3, 'sequential' => 1, 'contact_id' => $related_contact['contact_id']));
          foreach ( $contributions['values'] as $contribution ) {
            $links = array(
              CRM_Core_Action::VIEW => array(
                'name' => ts('View'),
                'url' => 'civicrm/contact/view/contribution',
                'qs' => 'reset=1&id=%%id%%&cid=%%cid%%&honorId=%%honorId%%&action=view&context=%%cxt%%&selectedChild=contribute',
                'title' => ts('View Contribution'),
              ),
              CRM_Core_Action::UPDATE => array(
                'name' => ts('Edit'),
                'url' => 'civicrm/contact/view/contribution',
                'qs' => 'reset=1&action=update&id=%%id%%&cid=%%cid%%&honorId=%%honorId%%&context=%%cxt%%&subType=%%contributionType%%',
                'title' => ts('Edit Contribution'),
              ),
              CRM_Core_Action::DELETE => array(
                'name' => ts('Delete'),
                'url' => 'civicrm/contact/view/contribution',
                'qs' => 'reset=1&action=delete&id=%%id%%&cid=%%cid%%&honorId=%%honorId%%&context=%%cxt%%',
                'title' => ts('Delete Contribution'),
              ),
            );
            $civilinks = CRM_Core_Action::formLink($links, NULL, array('cid' => $contribution['contact_id'], 'id' => $contribution['id']));
            $content2 .= '
              <tr id="rowid' . $contribution['id'] . '" class="odd-row crm-contribution_' . $contribution['id'] . '">
                <td class="right crm-contribution-amount"><span class="nowrap">' . CRM_Utils_System::href($contribution['display_name'], 'civicrm/contact/view/', 'reset=1&cid=' . $contribution['contact_id'])), false) . '</span> </td>
                <td class="right crm-contribution-amount"><span class="nowrap">' . $related_contact['relationship_name'] . '</span> </td>
                <td class="right bold crm-contribution-amount"><span class="nowrap">' . $contribution['total_amount'] . '</span> </td>
                <td class="crm-contribution-type crm-contribution-type_ crm-financial-type crm-financial-type_">' . $contribution['financial_type'] . '</td>
                <td class="crm-contribution-source">' . $contribution['contribution_source'] . '</td>
                <td class="crm-contribution-receive_date">' . $contribution['receive_date'] . '</td>
                <td class="crm-contribution-thankyou_date">' . $contribution['thankyou_date'] . '</td>
                <td class="crm-contribution-status">' . $contribution['contribution_status'] . '</td>
                <td class="crm-contribution-product_name">' . $contribution['product_name'] . '</td>
                <td><span>' . $civilinks . '</span></td>
              </tr>';
          }
        }

        $content2 .= '</table>';

        $content = $content1 . $content2 . $content3;
      }
    }
  }
}

/**
 * Implementation of hook_civicrm_pageRun
 */
#function addcontributiontabs_civicrm_pageRun(&$page) {
#  print_r($page);
#
#}

/**
 * Implementation of hook_civicrm_config
 */
function addcontributiontabs_civicrm_config(&$config) {
  _addcontributiontabs_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 */
function addcontributiontabs_civicrm_xmlMenu(&$files) {
  _addcontributiontabs_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 */
function addcontributiontabs_civicrm_install() {
  return _addcontributiontabs_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 */
function addcontributiontabs_civicrm_uninstall() {
  return _addcontributiontabs_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 */
function addcontributiontabs_civicrm_enable() {
  return _addcontributiontabs_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 */
function addcontributiontabs_civicrm_disable() {
  return _addcontributiontabs_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 */
function addcontributiontabs_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _addcontributiontabs_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 */
function addcontributiontabs_civicrm_managed(&$entities) {
  return _addcontributiontabs_civix_civicrm_managed($entities);
}
