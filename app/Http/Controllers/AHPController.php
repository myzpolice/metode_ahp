<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\V1\Models\Criteria;
use App\V1\Models\Alternative;
use App\V1\Models\DataAlternative;
use App\V1\Models\CriteriaComparison;
use App\V1\Models\RandomConsistencyIndex;

class AHPController extends Controller
{

    public function get_ahp_matrix_criteria(){
      $criteria = Criteria::orderBy('id', 'asc')->get();

      $criteria_ids = array();

      foreach($criteria as $key => $val){
        $criteria_ids[] = $criteria[$key]['id'];
      }

      $matrix = $this->ahp_matrix_criteria($criteria_ids);
      $number_of_column = $this->ahp_number_of_column($criteria_ids, $matrix);
      $sum = $this->ahp_sum($criteria_ids, $number_of_column);
      $norm_matrix = $this->ahp_norm_matrix_criteria($criteria_ids, $matrix, $sum);
      $number_of_row = $this->ahp_number_of_row($criteria_ids, $norm_matrix);
      $eigen_vektor = $this->ahp_eigen_vektor($criteria_ids, $norm_matrix);
      $sum_amaks = $this->ahp_amaks($criteria_ids, $matrix, $eigen_vektor);
      $t = $this->ahp_t($criteria_ids, $sum_amaks, $eigen_vektor);
      $ci = $this->ahp_ci($criteria_ids, $t);
      $rci = $this->ahp_rci($criteria_ids);
      $consistency = $this->ahp_consitency($ci, $rci);

      $res['t'] = $t;
      $res['ci'] = $ci;
      $res['rci'] = $rci->index_value;
      $res['consistency'] = $consistency;

      return view('ahp.index', compact('criteria', 'matrix', 'sum', 'norm_matrix', 'number_of_row', 'eigen_vektor', 'sum_amaks', 'res'));
      //return response($res);
    }

    public function get_ahp_matrix_alternative(){
      $alternative = Alternative::orderBy('id', 'asc')->get();
      $criteria = Criteria::orderBy('id', 'asc')->get();

      $alternative_ids = array();

      foreach($alternative as $key => $val){
        $alternative_ids[] = $alternative[$key]['id'];
      }

      foreach ($criteria as $key => $value) {
        $data_alternative = DataAlternative::with('alternative')->orderBy('alternative_id','asc')->where('criteria_id',$criteria[$key]['id'])->get();
        $matrix[$key]['criteria_id'] = $criteria[$key]['id'];
        $matrix[$key]['criteria_name'] = $criteria[$key]['criteria'];
        $matrix[$key]['result'] = $this->ahp_matrix_alternative($alternative_ids, $data_alternative);
        //$test[] = $data_alternative;
      }

      return view('ahp.index_alternative', compact('matrix','alternative'));
      //return response($matrix);
    }

    public function ahp_matrix_criteria($criteria_id){
      for($x = 0; $x < count($criteria_id); $x++){
        for($y = 0; $y < count($criteria_id);$y++){
            if($x == $y){
              $matrix[$x][$y] = 1;
            } else {
              if($x < $y){
              $q = CriteriaComparison::with('criteria1', 'criteria2', 'importance_level')->where(
                ['criteria_id_1' => $criteria_id[$x],
                 'criteria_id_2' => $criteria_id[$y]
               ])->first();
               if(count($q) > 0){
                 $nilai = $q->importance_level->level_value;
                 $matrix[$x][$y] = $nilai;
                 $matrix[$y][$x] = round((1/$nilai),3);
               } else {
                 $matrix[$x][$y] = 1;
                 $matrix[$y][$x] = 1;
               }
               }
             }
            }
      }
      return $matrix;
    }

    public function ahp_number_of_column($criteria_id, $matrix){
      for($x = 0; $x < count($criteria_id); $x++){
        for($y = 0; $y < count($criteria_id); $y++){
          $number_of_column[$x][$y] = $matrix[$y][$x];
        }
      }
      return $number_of_column;
    }

    public function ahp_norm_matrix_criteria($criteria_id, $matrix, $sum){
      for($x = 0; $x < count($criteria_id); $x++){
        for($y = 0; $y < count($criteria_id); $y++){
          $norm_matrix[$x][$y] = round($matrix[$x][$y] / $sum[$y], 3);
        }
      }
      return $norm_matrix;
    }

    public function ahp_sum($criteria_id, $number_of_column){
      for($x = 0; $x < count($criteria_id); $x++){
        $sum[] = array_sum($number_of_column[$x]);
      }
      return $sum;
    }

    public function ahp_number_of_row($criteria_id, $norm_matrix){
      for($x = 0; $x < count($criteria_id); $x++){
          $number_of_row[] = array_sum($norm_matrix[$x]);
      }
      return $number_of_row;
    }

    public function ahp_eigen_vektor($criteria_id, $norm_matrix){
      for($x = 0; $x < count($criteria_id); $x++){
          $eigen_vektor[] = array_sum($norm_matrix[$x])/count($criteria_id);
      }
      return $eigen_vektor;
    }

    public function ahp_amaks($criteria_id, $matrix, $eigen_vektor){
      for($x = 0; $x <  count($criteria_id); $x++){
        for($y = 0; $y < count($criteria_id); $y++){
          $amaks[$x][$y] = $matrix[$x][$y] * $eigen_vektor[$y];
        }
      }

      for($x = 0; $x <  count($criteria_id); $x++){
          $sum_amaks[] = array_sum($amaks[$x]);
      }
      return $sum_amaks;
    }

    public function ahp_t($criteria_id, $sum_amaks, $eigen_vektor){
      for($x = 0; $x < count($criteria_id); $x++){
        $t[] = $sum_amaks[$x] / $eigen_vektor[$x];
      }

      $sum_t = round(array_sum($t) / count($criteria_id), 3);

      return $sum_t;
    }

    public function ahp_ci($criteria_id, $sum_t){
      $ci = round(($sum_t - count($criteria_id))/(count($criteria_id) - 1), 3);

      return $ci;
    }

    public function ahp_rci($criteria_id){

      $index = count($criteria_id);

      $rci = RandomConsistencyIndex::where('total_index', $index)->first();

      return $rci;

    }

    public function ahp_consitency($ci, $rci){
      $value = round($ci / $rci->index_value, 3);
      if($value < 0.100){
        $res['value'] = $value;
        $res['consistency'] = true;
      } else {
        $res['value'] = $value;
        $res['consistency'] = false;
      }

      return $res;
    }

    public function ahp_matrix_alternative($alternative_ids, $data_alternative){
      for($x=0;$x<count($alternative_ids);$x++){
    		for($y=0;$y<count($alternative_ids);$y++){
    			$matrix[$x][$y] = round($data_alternative[$x]['value']/$data_alternative[$y]['value'],3);
    		}
    	}
    	return $matrix;
    }
}