<?php

/**
 * @file
 * Add a table of contributions from related contacts.
 *
 * Copyright (C) 2013-15, AGH Strategies, LLC <info@aghstrategies.com>
 * Licensed under the GNU Affero Public License 3.0 (see LICENSE.txt)
 */

require_once 'addcontributiontabs.civix.php';

/**
 * Implementation of hook_civicrm_alterContent
 */
function addcontributiontabs_civicrm_alterContent(&$content, $context, $tplName, &$object) {
  if ($context == 'page') {
    if ($tplName == 'CRM/Contribute/Page/Tab.tpl') {
      if ($object->getVar('_action') == 16) {
        $marker1 = strpos($content, 'thead');
        $marker = strpos($content, '</form', $marker1);

        $content1 = substr($content, 0, $marker);
        $content3 = substr($content, $marker);
        $content2 = '
          <h3>' . ts('Related Contributions') . '</h3>

          <table class="selector row-highlight">
            <thead class="sticky">
              <tr>
                <th scope="col">' . ts('Related Contact') . '</th>
                <th scope="col">' . ts('Relationship') . '</th>
                <th scope="col">' . ts('Amount') . '</th>
                <th scope="col">' . ts('Type') . '</th>
                <th scope="col">' . ts('Source') . '</th>
                <th scope="col">' . ts('Received') . '</th>
                <th scope="col">' . ts('Thank-you Sent') . '</th>
                <th scope="col">' . ts('Status') . '</th>
                <th scope="col">' . ts('Premium') . '</th><th scope="col"></th>
              </tr>
            </thead>';
        $contact_id = $object->getVar('_contactId');

        // An array to hold the contacts who are related.
        $related_contact_ids = array();

        try {
          $relTypeResult = civicrm_api3('RelationshipType', 'get', array('options' => array('limit' => 0)));
          foreach ($relTypeResult['values'] as $relTypeID => $relType) {
            // TODO: add interface to choose these.
            $typesToShow = array(
              'Employee of',
              'Head of Household for',
              'Household Member of',
            );
            if (!in_array($relType['name_a_b'], $typesToShow)) {
              unset($relTypeResult['values'][$relTypeID]);
            }
          }
          $relTypes = $relTypeResult['values'];
        }
        catch (CiviCRM_API3_Exception $e) {
          CRM_Core_Error::debug_log_message('API Error finding relationship types: ' . $e->getMessage());
        }

        // Get relationships where this contact is "A":
        $params = array(
          'sequential' => 1,
          'contact_id_a' => $contact_id,
          'relationship_type_id' => array('IN' => array_keys($relTypes)),
          'options' => array('limit' => 0),
        );
        addcontributiontabs_find_relationships($params, $related_contact_ids, $relTypes);

        // Get relationships where this contact is "B":
        unset($params['contact_id_a']);
        $params['contact_id_b'] = $contact_id;
        addcontributiontabs_find_relationships($params, $related_contact_ids, $relTypes);

        // Template for the links in the table of contributions.
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

        $rows = array();
        $toggle = 'even';
        foreach ($related_contact_ids as $related_contact) {
          $contribParams = array(
            'contact_id' => $related_contact['contact_id'],
            'options' => array('sort' => "receive_date DESC", 'limit' => 0),
          );
          try {
            $contributions = civicrm_api3('Contribution', 'get', $contribParams);
            foreach ($contributions['values'] as $contribution) {
              $civilinks = CRM_Core_Action::formLink($links, NULL, array('cid' => $contribution['contact_id'], 'id' => $contribution['id']));
              $toggle = ($toggle == 'odd') ? 'even' : 'odd';
              $rows[] = '<tr id="rowid' . $contribution['id'] . '" class="' . $toggle . '-row crm-contribution_' . $contribution['id'] . '">
                  <td class="right crm-contribution-amount"><span class="nowrap">' . CRM_Utils_System::href($contribution['display_name'], 'civicrm/contact/view/', 'reset=1&cid=' . $contribution['contact_id'], FALSE) . '</span> </td>
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
          catch (CiviCRM_API3_Exception $e) {
            CRM_Core_Error::debug_log_message('API Error finding contributions: ' . $e->getMessage());
          }
        }

        $content2 = empty($rows) ? '' : $content2 . implode("\n", $rows) . '</table>';

        $content = $content1 . $content2 . $content3;
      }
    }
  }
}

/**
 * Find the relationships from the contact.
 *
 * @param array $params
 *   Valid API params.
 * @param array &$related_contact_ids
 *   The contact IDs gathered so far.
 * @param array $relTypes
 *   The available relationship types.
 */
function addcontributiontabs_find_relationships($params, &$related_contact_ids, $relTypes) {
  try {
    $relationships = civicrm_api3('Relationship', 'get', $params);
    foreach ($relationships['values'] as $relationship) {
      $relType = &$relTypes[$relationship['relationship_type_id']];
      $related_contact_ids[] = array(
        'contact_id' => empty($params['contact_id_a']) ? $relationship['contact_id_a'] : $relationship['contact_id_b'],
        'relationship_name' => empty($params['contact_id_a']) ? $relType['label_a_b'] : $relType['label_b_a'],
      );
    }
  }
  catch (CiviCRM_API3_Exception $e) {
    CRM_Core_Error::debug_log_message('API Error finding relationships: ' . $e->getMessage());
  }
}

/**
 * Implementation of hook_civicrm_config
 */
function addcontributiontabs_civicrm_config(&$config) {
  _addcontributiontabs_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_install
 */
function addcontributiontabs_civicrm_install() {
  return _addcontributiontabs_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_enable
 */
function addcontributiontabs_civicrm_enable() {
  return _addcontributiontabs_civix_civicrm_enable();
}

// /**
//  * Implements hook_civicrm_postInstall().
//  *
//  * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_postInstall
//  */
// function addcontributiontabs_civicrm_postInstall() {
//   _addcontributiontabs_civix_civicrm_postInstall();
// }

// /**
//  * Implements hook_civicrm_entityTypes().
//  *
//  * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_entityTypes
//  */
// function addcontributiontabs_civicrm_entityTypes(&$entityTypes) {
//   _addcontributiontabs_civix_civicrm_entityTypes($entityTypes);
// }
