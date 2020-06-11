<?php
/**
 * @author Klaas Eikelboom  <klaas.eikelboom@civicoop.org>
 * @date 09-Jun-2020
 * @license  AGPL-3.0
 */
namespace Drupal\jv_pivot_block\Plugin\Block;

use Civi\ActionProvider\Condition\ParameterHasValue;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'JvPivotBlock' block.
 *
 * @Block(
 *  id = "jv_pivot_block",
 *  admin_label = @Translation("josiahventure pivot block"),
 * )
 */
class JvPivotBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal\cmrf_core\Core definition.
   *
   * @var \Drupal\cmrf_core\Core
   */
  protected $core;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->core = $container->get('cmrf_core.core');
    return $instance;
  }

  public function translateOptions($data){
    $result = [];
    foreach($data as $row){
      if(isset($row[$this->configuration['labelColumn']]) && isset($this->configuration['labelOptions'])){
        $lookup = $row[$this->configuration['labelColumn']];
        $options = $this->configuration['labelOptions'];
        if(key_exists($lookup,$options)){
          $row[$this->configuration['labelColumn']] = $options[$lookup];
        }
      }
      if(isset($row[$this->configuration['headerColumn']]) && isset($this->configuration['headerOptions'])){
        $lookup = $row[$this->configuration['headerColumn']];
        $options = $this->configuration['headerOptions'];
        if(key_exists($lookup,$options)){
          $row[$this->configuration['headerColumn']] = $options[$lookup];
        }
      }
      $result[]=$row;
    }
    return $result;
  }

  public function duplicate($data,$column){
    $result=[];
    foreach($data as $row){
      $values = explode(',',$row[$column]);
      foreach($values as $value){
        $row[$column] = $value;
        $result[]=$row;
      }
    }
    return $result;
  }

  /**
   * Formats and returns the pivot table
   *
   * @return array
   */
  public function pivot($data ){
    $pivotTable = [];
    $pivotcell1=$this->configuration['pivotCell_1'];
    $pivotcell2=$this->configuration['pivotCell_2'];

    $labels =  $labelsCount = $headers = [];
    foreach($data as $row){
      $label = $row[$this->configuration['labelColumn']];
      $header = $row[$this->configuration['headerColumn']];
      if(!in_array($label,$labels)){
        $labels[]= $label;
      }
      if(!in_array($header,$headers)){
        $headers[]= $header;
      };
      $values = [$row[$pivotcell1],$row[$pivotcell2]];
      if(!isset($pivotTable[$label])){
        $pivotTable[$label] = [];
      }
      $pivotTable[$label][$header][]= $values;
    }
    $pivotTableResult = [];
    $rownum = 0;
    foreach($labels as $label){
      $max=0;
      $pivotTableResult[$rownum++]=[];
      foreach($headers as $header){
        if(isset($pivotTable[$label][$header])) {
          $max = max(count($pivotTable[$label][$header]) - 1, $max);
        }
      }
      for($i=0;$i<= $max; $i++){
        $pivotTableResult[$rownum][]=$i==0?$label:"";
        foreach($headers as $header){
          list($cell1,$cell2) = isset($pivotTable[$label][$header][$i])?$pivotTable[$label][$header][$i]:["",""];
          $pivotTableResult[$rownum][] = $cell1;
          $pivotTableResult[$rownum][] = $cell2;
        }
        $rownum++;
      }
    }
    asort($headers);
    $headerResult[]=[''];  // upper left corner is empty
    foreach($headers as $header){
      // two cells are shown with the seam heading
      // so span this heading accros two collumns
      $headerResult[]=['data'=>$header,'colspan'=>2];
    }
    return [$headerResult,$pivotTableResult];
  }

  /**
   * Reads the data from the configured connection
   * @return mixed
   */
  public function readData(){
    $call = $this->core->createCall(
      $this->configuration['connection'],
      $this->configuration['entity'],
      'get',
      \Drupal::request()->query->all(),
      ['sort' => [$this->configuration['labelColumn'],$this->configuration['headerColumn']]],
      null);
    $result = $this->core->executeCall($call);
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    if ($this->configuration['connection']) {
      $result = $this->readData();
      if ($result['is_error']) {
        $build['#markup'] = t('Api Error @m', ['@m' => $result['error_message']]);
      }
      else {
        $data = $result['values'];
        $data = $this->duplicate($data,$this->configuration['labelColumn']);
        $data = $this->duplicate($data,$this->configuration['headerColumn']);
        $data = $this->translateOptions($data);
        list($headers, $pivotTable) = $this->pivot($data);
        $build[] = [
          '#type' => 'table',
          '#header' => $headers,
          '#rows' => $pivotTable,
        ];
      }
    }
    else {
      $build['#markup'] = t('No connection configured, no pivot table shown');
    }
    return $build;
  }

  /**
   * Configure the block - you can opt for the connection - the dataprocessor
   * is hardcoded.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array|mixed
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $build['connection'] = [
      '#type' => 'select',
      '#title' => $this->t('Connector'),
      '#empty_option' => $this->t('- None -'),
      '#options' => $this->core->getConnectors(),
      '#default_value' => $this->configuration['connection'],
    ];
    $build['entity'] = [
      '#type' => 'textfield',
      '#title' => t('Api Entity'),
      '#default_value' => isset($this->configuration['entity'])?$this->configuration['entity']:'activityministrymapping',
    ];
    $build['labelColumn'] = [
      '#type' => 'textfield',
      '#title' => t('Label Column'),
      '#default_value' =>  isset($this->configuration['labelColumn'])?$this->configuration['labelColumn']:'frequency',
    ];
    $build['headerColumn'] = [
      '#type' => 'textfield',
      '#title' => t('Header Column'),
      '#default_value' => isset($this->configuration['headerColumn'])?$this->configuration['headerColumn']:'phases',
    ];
    $build['pivotCell_1'] = [
      '#type' => 'textfield',
      '#title' => t('Pivot Cell 1'),
      '#default_value' => isset($this->configuration['pivotCell_1'])?$this->configuration['pivotCell_1']:'event_name',
    ];
    $build['pivotCell_2'] = [
      '#type' => 'textfield',
      '#title' => t('Pivot Cell 2'),
      '#default_value' => isset($this->configuration['pivotCell_2'])?$this->configuration['pivotCell_2']:'participants',
    ];
    return $build;
  }

  /**
   * Save the configuration form
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->configuration['connection']=$values['connection'];
    $this->configuration['entity']=$values['entity'];
    $this->configuration['labelColumn']=$values['labelColumn'];
    $this->configuration['headerColumn']=$values['headerColumn'];
    $this->configuration['pivotCell_1']=$values['pivotCell_1'];
    $this->configuration['pivotCell_2']=$values['pivotCell_2'];

    if(isset($this->configuration)){
      $call = $this->core->createCall(
        $this->configuration['connection'],
        $this->configuration['entity'],
        'getfields',
        ['api_action' => "get"],
        null,
        null);
      $result = $this->core->executeCall($call);
      if(!$result['is_error']){
        if(isset($result['values'][$this->configuration['labelColumn']]) && isset($result['values'][$this->configuration['labelColumn']]['options'])){
          $this->configuration['labelOptions'] = $result['values'][$this->configuration['labelColumn']]['options'];
        }
        if(isset($result['values'][$this->configuration['headerColumn']]) && isset($result['values'][$this->configuration['headerColumn']]['options'])){
          $this->configuration['headerOptions'] = $result['values'][$this->configuration['headerColumn']]['options'];
        }
      }
    }
  }

  /**
   * No caching, each refresh the latest data is shown.
   * @return int
   */
  public function getCacheMaxAge() {
    return 0;
  }

}
