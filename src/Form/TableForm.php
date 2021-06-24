<?php

namespace Drupal\levchik\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class main TableForm.
 */
class TableForm extends FormBase {

  /**
   * @var string[]
   *  Array of tables headers.
   */
  private $headers = [
    'Year',
    'Jan',
    'Feb',
    'Mar',
    'Q1',
    'Apr',
    'May',
    'Jun',
    'Q2',
    'Jul',
    'Aug',
    'Sep',
    'Q3',
    'Oct',
    'Nov',
    'Dec',
    'Q4',
    'YTD',
  ];

  /**
   * @var string[]
   *  Array of form tables by id and tables rows.
   *
   *  Eg:
   * $this->tableRows = [
   *   0 => 1,
   *   1 => 3,
   * ];
   * Which means table with id 0 has one row and table 1 has 3 rows.
   */
  private $tableRows;

  /**
   * @var string[]
   *  Additional array of year quarters.
   */
  private $quarters = [
    1 => [
      'Jan',
      'Feb',
      'Mar',
    ],
    2 => [
      'Apr',
      'May',
      'Jun',
    ],
    3 => [
      'Jul',
      'Aug',
      'Sep',
    ],
    4 => [
      'Oct',
      'Nov',
      'Dec',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'table_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $this->tableRows = is_null($this->tableRows) ? [
      1 => 1,
    ] : $this->tableRows;
    $form['#attributes'] = [
      'id' => 'my-form',
    ];
    $form['#tree'] = TRUE;
    $form['tables_container'] = [
      '#type' => 'container',
    ];
    $form['tables_container']['#attributes']['id'] = 'tables_container';
    foreach ($this->tableRows as $table => $rows) {
      $this->buildTable($form, $table);
    }
    $form['actions'] = [];
    $form['actions']['addtable'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add table'),
      '#submit' => ['::formAddTable'],
      '#name' => 'add-table',
      '#ajax' => [
        'callback' => '::ajaxCallback',
        'wrapper' => 'my-form',
      ],
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#ajax' => [
        'callback' => '::ajaxCallback',
        'wrapper' => 'my-form',
      ],
    ];
    $form['#attached']['library'][] = 'levchik/table-styling';
    $form_state->set('tableRows', $this->tableRows);
    return $form;
  }

  /**
   * Builds a single table for form.
   */
  private function buildTable(array &$form, int $tableId) {
    $form['tables_container'][$tableId] = [
      'year_table' => [
        '#type' => 'table',
        '#header' => $this->headers,
        '#attributes' => [
          'id' => $tableId,
        ],
        '#rows' => [],
      ],
      'actions' => [
        '#type' => 'actions',
        'add_row' => [
          '#type' => 'submit',
          '#data' => $tableId,
          '#name' => 'addButton_' . $tableId,
          '#value' => $this->t('Add row'),
          '#submit' => ['::formAddRow'],
          '#ajax' => [
            'callback' => '::ajaxCallback',
            'wrapper' => 'my-form',
          ],
        ],
      ],
    ];
    $this->buildRows($form, $tableId);
    return $form;
  }

  /**
   * Builds rows for a single table based on row count from $this->tableRows.
   */
  private function buildRows(&$form, $tableId) {
    $year = date('Y');
    for ($i = $this->tableRows[$tableId]; $i > 0; $i--) {
      $result = [];
      $result['year'] = [
        '#markup' => $year - $i + 1,
      ];
      for ($j = 1; $j <= 4; $j++) {
        foreach ($this->quarters[$j] as $month) {
          $result[$month] = [
            '#type' => 'number',
          ];
        }
        $result[$j] = [
          '#prefix' => '<span class="result">',
          '#suffix' => '</span>',
          '#markup' => '',
        ];
      }
      $result['result'] = [
        '#prefix' => '<span class="result">',
        '#suffix' => '</span>',
        '#markup' => '',
      ];
      $form['tables_container'][$tableId]['year_table'][$i] = $result;
    }
    return $form;
  }

  /**
   * Helper function for AJAX to work.
   */
  function ajaxCallback(array &$form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * AJAX function to add rows to particular table by rebuilding it.
   */
  function formAddRow(array &$form, FormStateInterface $form_state) {
    $id = $form_state->getTriggeringElement()['#data'];
    $this->tableRows = $form_state->get('tableRows');
    ++$this->tableRows[$id];
    $form_state->set('tableRows', $this->tableRows);
    $form_state->setRebuild(TRUE);
  }

  /**
   * AJAX function to add another table to form by rebuilding it.
   */
  function formAddTable(array &$form, FormStateInterface $form_state) {
    $this->tableRows = $form_state->get('tableRows');
    $this->tableRows[] = 1;
    $form_state->set('tableRows', $this->tableRows);
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $valid = $this->validateTables($form_state);
    $messenger = \Drupal::messenger();
    $messenger->addMessage($valid ? "Valid" : "Invalid", $valid ? $messenger::TYPE_STATUS : $messenger::TYPE_ERROR);
    if ($valid) {
      $this->processTable($form, $form_state);
    }
    return $form;
  }

  /**
   * Form validation before processing form results.
   */
  private function validateTables(FormStateInterface $form_state) {
    $tablesMergedRows = [];
    $tablesFirstNLastNotBlank = [];
    $filteredTables = [];
    $values = $form_state->getValues()['tables_container'];

    foreach ($values as $table_key => $table) {
      $tablesMergedRows[$table_key] = [];
      // Clear first empty rows.
      for ($blankRow = count($table['year_table']); (count(array_flip($table['year_table'][$blankRow])) === 1 && end($table['year_table'][$blankRow]) === "") && $blankRow > 0 ; $blankRow--) {
        unset($table['year_table'][$blankRow]);
      }
      // Check if cleared table not blank.
      if (empty($table['year_table'])) {
        return FALSE;
      }
      // Make one-dimensional array from table rows.
      foreach ($table['year_table'] as $row) {
        $tablesMergedRows[$table_key] = array_merge($tablesMergedRows[$table_key], array_values($row));
      }
      // Make an array without blank cells.
      $filteredTables[$table_key] = array_filter($tablesMergedRows[$table_key], function ($item) {
        return $item !== "";
      });
    }

    foreach ($tablesMergedRows as $table_key => $table) {
      $arr_length = count($table);
      // Find indexes of first & last not blank table cells.
      for ($firstNotBlank = 0; $firstNotBlank < $arr_length && $table[$firstNotBlank] === ""; $firstNotBlank++);
      for ($lastNotBlank = $arr_length - 1; $lastNotBlank >= 0 && $table[$lastNotBlank] === ""; $lastNotBlank--);
      $tablesFirstNLastNotBlank[$table_key]['first'] = $firstNotBlank == $arr_length ? NULL : $firstNotBlank;
      $tablesFirstNLastNotBlank[$table_key]['last'] = $lastNotBlank == -1 ? NULL : $lastNotBlank;

      // Check if filled cells sequence has a break.
      if ((array_key_last($filteredTables[$table_key]) - array_key_first($filteredTables[$table_key]) + 1) != count($filteredTables[$table_key])) {
        return FALSE;
      }

      // Check if tables has the same months intervals.
      if((count($tablesMergedRows[1]) != count($tablesMergedRows[$table_key])) || ($tablesFirstNLastNotBlank[1]['first'] != $tablesFirstNLastNotBlank[$table_key]['first']) || ($tablesFirstNLastNotBlank[1]['last'] != $tablesFirstNLastNotBlank[$table_key]['last'])) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Form results processing and display.
   */
  private function processTable(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues()['tables_container'];
    foreach ($values as $table_key => &$table) {
      $table = $table['year_table'];
      foreach ($table as $year_key => $year) {
        $year_value = 0;
        foreach ($this->quarters as $quarter_key => $quarter) {
          $quarter_value = 0;
          foreach ($quarter as $month) {
            $quarter_value += (int) $year[$month];
          }
          $quarter_value = $quarter_value === 0 ? $quarter_value : ($quarter_value + 1) / 3;
          $year_value += $quarter_value;
          $form['tables_container'][$table_key]['year_table'][$year_key][$quarter_key]['#markup'] = round($quarter_value, 2);
        }
        $year_value = $year_value === 0 ? $year_value : ($year_value + 1) / 4;
        $form['tables_container'][$table_key]['year_table'][$year_key]['result']['#markup'] = round($year_value, 2);
      }
    }
    return $form;
  }

}
