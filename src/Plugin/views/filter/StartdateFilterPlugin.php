<?php
/**
 * Created by PhpStorm.
 * User: ddiallo
 * Date: 1/10/20
 * Time: 3:19 PM
 */

namespace Drupal\start_enddate_filter\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\Display\DisplayPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Views;

/**
 * Start month filter of the report
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("custom_start_month_filter")
 */
class StartdateFilterPlugin extends FilterPluginBase {

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);
    $this->definition['options callback'] = [$this, 'generateOptions'];
  }

  /**
   * {@inheritdoc}
   */
  public function canExpose() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isExposed() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['exposed_year'] = ['default' => []];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  //  public function buildExposedForm(&$form, FormStateInterface $form_state) {
  //
  //    parent::buildExposedForm($form, $form_state);
  //
  //    $selectedYearsId = $this->options['exposed_year']; //Got year code
  //    $selectedYears = [];
  //    //drupal_set_message(json_encode($this->options['exposed_year'])." Exposed date");
  //    foreach ($selectedYearsId as $yKey => $yValue) {
  //      $selectedYears[$yKey] = $this->getTaxoByTid($yKey);
  //    }
  //    //drupal_set_message(json_encode($selectedYears)." Exposed date");
  //    $combineOptions = $this->getCombinedPeriod($selectedYears);
  //
  //
  //    $form['exposed_start_date'] = [
  //      '#type' => 'select',
  //      '#title' => $this->t('The start date of the report'),
  //      '#description' => $this->t('Select the month you want as start date.'),
  //      '#options' => $combineOptions,
  //      '#default_value' => $this->options['exposed_start_date'],
  //    ];
  //    if ($this->options['expose']['multiple']) {
  //      $form['exposed_start_date']['#multiple'] = TRUE;
  //    }
  //    //drupal_set_message(json_encode($this->options['exposed_year'])." Gnaly");
  ////    drupal_set_message(json_encode($this->options['expose']['multiple'])." Option multiple");
  //  }

  protected function valueForm(&$form, FormStateInterface $form_state) {

    $selectedYearsId = $this->options['exposed_year'];
    $selectedYears = [];

    foreach ($selectedYearsId as $yKey => $yValue) {
      $selectedYears[$yKey] = $this->getTaxoByTid($yKey);
    }
    $combineOptions = $this->getCombinedPeriod($selectedYears);

    $form['value'] = [
      '#tree' => TRUE,
      '#type' => 'select',
      '#title' => $this->t('The start date of the report'),
      '#description' => $this->t('Select the month you want as start date.'),
      '#options' => $combineOptions,
      '#default_value' => !empty($this->value) ? $this->value : '2513',
      '#wrapper_attributes' => [
        'style' => ['max-width:15%']
      ]
    ];
    if ($this->options['expose']['multiple']) {
      $form['value']['#multiple'] = TRUE;
    }
  }


  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    //get year
    $yearOptions = $this->getTaxoByVid('year');

    $form['exposed_year'] = [
      '#type' => 'select',
      '#title' => $this->t('Years'),
      '#description' => $this->t('Select the years to include'),
      '#options' => $yearOptions,
      '#multiple' => TRUE,
      '#default_value' => $this->options['exposed_year'],
    ];
  }

  public function acceptExposedInput($input) {
    return TRUE;
  }

  /**
   * Applying query filter. If you turn on views query debugging you should see
   * these clauses applied. If the filter is optional, and nothing is selected,
   * this code will never be called.
   */
  public function query() {
    $this->ensureMyTable();

    $exposedInput = $this->view->getExposedInput();
    $startMonthYear = $exposedInput['start_filter'] ?? '';
    $endMonthYear = $exposedInput['end_filter'] ?? '';
    $startMonth = substr($startMonthYear, 0, 2);
    $startYear = substr($startMonthYear, -2);
    $endMonth = substr($endMonthYear, 0, 2);
    $endYear = substr($endMonthYear, -2);

    $yearField = "node__field_year.field_year_target_id";
    $monthField = "node__field_month.field_month_target_id";
    $yearLangField = "node__field_year.langcode";
    $monthLangField = "node__field_month.langcode";
    $nodeLangField = "node_field_data.langcode";

    //Join tables
    $yearConfiguration = [
      'table' => 'node__field_year',
      'field' => 'entity_id',
      'left_table' => 'node_field_data',
      'left_field' => 'nid',
      'operator' => '=',
    ];

    //Config for month
    $monthConfiguration = [
      'table' => 'node__field_month',
      'field' => 'entity_id',
      'left_table' => 'node_field_data',
      'left_field' => 'nid',
      'operator' => '=',
    ];

    $yearJoin = Views::pluginManager('join')
      ->createInstance('standard', $yearConfiguration);
    $monthJoin = Views::pluginManager('join')
      ->createInstance('standard', $monthConfiguration);

    $this->query->addRelationship('node__field_year', $yearJoin, 'node_field_data');
    $this->query->addRelationship('node__field_month', $monthJoin, 'node_field_data');

    if ($this->verifyNormalPeriod($startMonth, $startYear, $endMonth, $endYear)) {
      if ($this->typeOfPeriod($startYear, $endYear) == 'same') {
        $monthsBetween = $this->getMonthsBetween($startMonth, $endMonth);
        $matches = implode(',', $monthsBetween);
        $this->query->addWhereExpression($this->options['group'], "$yearField = $startYear AND $monthField IN ($matches) AND $yearLangField = $nodeLangField AND $monthLangField = $nodeLangField");
      }
      elseif ($this->typeOfPeriod($startYear, $endYear) == 'two') {
        $months1 = $this->getMonthsUp($startMonth);
        $months2 = $this->getMonthsDown($endMonth);
        $matchesMonth1 = implode(',', $months1);
        $matchesMonth2 = implode(',', $months2);
        $this->query->addWhereExpression($this->options['group'], "($yearField = $startYear AND $monthField IN ($matchesMonth1) AND $yearLangField = $nodeLangField AND $monthLangField = $nodeLangField) OR
    ($yearField = $endYear AND $monthField IN ($matchesMonth2) AND $yearLangField = $nodeLangField AND $monthLangField = $nodeLangField)");
      }
      elseif ($this->typeOfPeriod($startYear, $endYear) == 'three') {
        $yearThird = $this->getYearThird($startYear, $endYear);
        $months1 = $this->getMonthsUp($startMonth);
        $months2 = $this->getAllMonths();
        $months3 = $this->getMonthsDown($endMonth);
        $matchesMonth1 = implode(',', $months1);
        $matchesMonth2 = implode(',', $months2);
        $matchesMonth3 = implode(',', $months3);
        $this->query->addWhereExpression($this->options['group'], "($yearField = $startYear AND $monthField IN ($matchesMonth1) AND $yearLangField = $nodeLangField AND $monthLangField = $nodeLangField) OR
    ($yearField = $yearThird AND $monthField IN ($matchesMonth2) AND $yearLangField = $nodeLangField AND $monthLangField = $nodeLangField) OR ($yearField = $endYear AND $monthField IN ($matchesMonth3) AND $yearLangField = $nodeLangField AND $monthLangField = $nodeLangField)");
      }
    }
    else {
      // todo return to start builder//With message that period is not normal
    }
  }

  public function getCombinedPeriod($years) {
    $months = $this->getTaxoByVid('month');
    $combinedPeriod = [];
    foreach ($years as $yKey => $yValue) {
      foreach ($months as $mKey => $mValue) {
        $combinedPeriod[$mKey . $yKey] = $mValue . " " . $yValue;
      }
    }

    return $combinedPeriod;
  }

  function getTaxoByVid($term) {
    $query = \Drupal::database()->select('taxonomy_term_field_data', 'td');
    $query->addField('td', 'tid');
    $query->addField('td', 'name');
    if ($term == 'year') {
      $query->condition('td.vid', 'year');
    }
    else {
      $query->condition('td.vid', 'month');
    }
    $query->condition('td.langcode', 'en');
    $terms = $query->execute();
    $tIdNames = $terms->fetchAll();
    $taxo = [];
    foreach ($tIdNames as $taxoObject) {
      $taxo[$taxoObject->tid] = $taxoObject->name;
    }
    ksort($taxo);

    return $taxo;
  }

  function getTaxoByTid($tid) {
    $query = \Drupal::database()->select('taxonomy_term_field_data', 'td');
    $query->addField('td', 'name');
    $query->condition('td.tid', $tid);
    // For better performance, define the vocabulary where to search.
    // $query->condition('td.vid', $vid);
    $term = $query->execute();
    $tname = $term->fetchField();
    return $tname;
  }

  function getMonthsBetween($start, $end) {
    //get tableau
    $months = $this->getTaxoByVid('month');
    //Take just the keys
    $months = array_keys($months);
    $range = array_slice($months, array_search($start, $months), array_search($end, $months) - array_search($start, $months) + 1);

    return $range;
  }

  function getMonthsUp($startMonth) {
    $months = $this->getTaxoByVid('month');
    //Take just the keys
    $months = array_keys($months);
    $range = array_slice($months, array_search($startMonth, $months), 12 - array_search($startMonth, $months));

    return $range;
  }

  function getMonthsDown($endMonth) {
    $months = $this->getTaxoByVid('month');
    $months = array_keys($months);
    $range = array_slice($months, 0, array_search($endMonth, $months) + 1);

    return $range;
  }

  function getAllMonths() {
    $months = $this->getTaxoByVid('month');
    $months = array_keys($months);

    return $months;
  }

  function getYearThird($startYear, $endYear) {
    $years = $this->getTaxoByVid('year');
    $years = array_keys($years);
    foreach ($years as $value) {
      if ($startYear < $value && $endYear > $value) {
        $year = $value;
      }
    }

    return $year;
  }

  function verifyNormalPeriod($startMonth, $startYear, $endMonth, $endYear) {
    if (($startYear < $endYear) || ($startYear == $endYear && $startMonth < $endMonth)) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  function typeOfPeriod($startYear, $endYear) {
    $startYear = intval($startYear);
    $endYear = intval($endYear);
    if ($startYear == $endYear) {
      return 'same';
    }
    elseif (($endYear - $startYear) == 1) {
      return 'two';
    }
    elseif (($endYear - $startYear) == 2) {
      return 'three';
    }
  }

}