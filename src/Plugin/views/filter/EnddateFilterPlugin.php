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

/**
 * End month filter of the report
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("custom_end_month_filter")
 */
class EnddateFilterPlugin extends FilterPluginBase {

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
  //    $form['exposed_end_date'] = [
  //      '#type' => 'select',
  //      '#title' => $this->t('The end date of the report'),
  //      '#description' => $this->t('Select the month you want as end date.'),
  //      '#options' => $combineOptions,
  //      '#default_value' => $this->options['exposed_end_date'],
  //    ];
  //    if ($this->options['expose']['multiple']) {
  //      $form['exposed_end_date']['#multiple'] = TRUE;
  //    }
  //    //drupal_set_message(json_encode($this->options['exposed_year'])." Gnaly");
  //    //    drupal_set_message(json_encode($this->options['expose']['multiple'])." Option multiple");
  //  }

  protected function valueForm(&$form, FormStateInterface $form_state) {

    $selectedYearsId = $this->options['exposed_year']; //Got year code
    $selectedYears = [];
    foreach ($selectedYearsId as $yKey => $yValue) {
      $selectedYears[$yKey] = $this->getTaxoByTid($yKey);
    }
    $combineOptions = $this->getCombinedPeriod($selectedYears);

    $form['value'] = [
      '#type' => 'select',
      '#title' => $this->t('The end date of the report'),
      '#description' => $this->t('Select the month you want as end date.'),
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
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['exposed_year'] = ['default' => []];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

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

  public function query() {
    //Leaving this empty
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
    $term = $query->execute();
    $tname = $term->fetchField();
    return $tname;
  }

}