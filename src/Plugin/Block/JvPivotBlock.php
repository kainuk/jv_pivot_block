<?php

namespace Drupal\jv_pivot_block\Plugin\Block;

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

  public function pivot(){
    $data = $this->readData();
    $pivotTable = [];
    $labelColumn='frequency';
    $headerColumn='phases';
    $pv1='event_name';
    $pv2='participants';
    // labels
    $labels =  $labelsCount = $headers = [];
    foreach($data as $row){
      $label = $row[$labelColumn];
      $header = $row[$headerColumn];
      if(!in_array($label,$labels)){
        $labels[]= $label;
      }
      if(!in_array($header,$headers)){
        $headers[]= $header;
      };
      $values = [$row[$pv1],$row[$pv2]];
      if(!isset($pivotTable[$label])){
        $pivotTable[$label] = [];
      }
      $pivotTable[$label][$header][]= $values;
    }
    $result = [];
    $rownum = 0;
    foreach($labels as $label){
      $max=0;
      $result[$rownum++]=[];
      foreach($headers as $header){
        if(isset($pivotTable[$label][$header])) {
          $max = max(count($pivotTable[$label][$header]) - 1, $max);
        }
      }
      for($i=0;$i<= $max; $i++){
        $result[$rownum][]=$i==0?$label:"";
        foreach($headers as $header){
          list($cell1,$cell2) = isset($pivotTable[$label][$header][$i])?$pivotTable[$label][$header][$i]:["",""];
          $result[$rownum][] = $cell1;
          $result[$rownum][] = $cell2;
        }
        $rownum++;
      }
    }

    $headerResult[]=[''];
    foreach($headers as $header){
      $headerResult[]=['data'=>$header,'colspan'=>2];
    }
    return [$headerResult,$result];
  }

  public function readData(){
    $call = $this->core->createCall($this->configuration['connection'],'activityministrymapping','get',\Drupal::request()->query->all(),['sort' => ['frequency','phases']],null);
    $result = $this->core->executeCall($call);
    return $result['values'];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    list($headers,$pivotTable) = $this->pivot();
    $build[]= [
      '#type' => 'table',
      '#header' => $headers,
      '#rows'   => $pivotTable,
    ];
    return $build;
  }

  public function blockForm($form, FormStateInterface $form_state) {

    $build['connection'] = [
      '#type' => 'select',
      '#title' => $this->t('Connector'),
      '#empty_option' => $this->t('- None -'),
      '#options' => $this->core->getConnectors(),
      '#default_value' => $this->configuration['connection'],
    ];
    return $build;
  }

  public function blockSubmit($form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->configuration['connection']=$values['connection'];
  }

  public function getCacheMaxAge() {
    return 0;
  }

}
