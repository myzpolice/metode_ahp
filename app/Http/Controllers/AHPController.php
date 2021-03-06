<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\V1\Models\Criteria;
use App\V1\Models\Alternative;
use App\V1\Models\DataAlternative;
use App\V1\Models\CriteriaComparison;
use App\V1\Models\RandomConsistencyIndex;
use App\V1\Models\RankSalary;
use App\V1\Models\AssessmentSummary;
use App\V1\Models\AssessmentCriteria;
use App\V1\Models\PairwiseComparison;
use App\V1\Models\Year;
use App\V1\Models\Division;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Redirect;

class AHPController extends Controller
{

    public function get_ahp_matrix_criteria(){
      $criteria = Criteria::orderBy('id', 'asc')->get();

      $count_criteria = count($criteria) - 1;
      $count_comparison = count(CriteriaComparison::all());
      $total_comparison = ((1*$count_criteria*$count_criteria)/2) + ((1*$count_criteria)/2);
      $criteria_ids = array();

      if(count($criteria) == 0 || $count_comparison < $total_comparison){
        $matrix = null;
        $number_of_column = null;
        $sum = null;
        $norm_matrix = null;
        $number_of_row = null;
        $eigen_vektor = null;
        $sum_amaks = null;
        $t = null;
        $ci = null;
        $rci = null;
        $consistency = null;

        $res['t'] = null;
        $res['ci'] = null;
        $res['rci'] = null;
        $res['consistency'] = null;
      } else {
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
        $res['rci'] = $rci;
        $res['consistency'] = $consistency;

        $pc = PairwiseComparison::all();
        if(count($pc) == 0){
          $pc_store = new PairwiseComparison();
          $pc_store->fill([
            't' => $res['t'],
            'ci' => $res['ci'],
            'rci_id' => $res['rci']['id'],
            'consistency' => $res['consistency']['consistency'],
            'consistency_value' => $res['consistency']['value'],
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
          ]);
          $pc_store->save();
        } else {
          $pc_edit = PairwiseComparison::first();
          $pc_edit->t = $res['t'];
          $pc_edit->ci = $res['ci'];
          $pc_edit->rci_id = $res['rci']['id'];
          $pc_edit->consistency = $res['consistency']['consistency'];
          $pc_edit->consistency_value = $res['consistency']['value'];
          $pc_edit->updated_by = Auth::id();
          $pc_edit->save();
        }
      }

      $title = 'Pairwise Comparison';

      //return view('ahp.index', compact('criteria', 'matrix', 'sum', 'norm_matrix', 'number_of_row', 'eigen_vektor', 'sum_amaks', 'res', 'title'));
      //return response(compact('criteria', 'matrix', 'sum', 'norm_matrix', 'number_of_row', 'eigen_vektor', 'sum_amaks', 'res'));
      return response($res);
      //return Redirect::route('criteria_comparison.index', compact('res'));
    }

    public function get_ahp_matrix_alternative(Request $request, $id){

      $year_id = $id;

      $alternative = Alternative::orderBy('id', 'asc')->get();
      $criteria = Criteria::orderBy('id', 'asc')->get();

      $d_alt = DB::select("select distinct alternative_id, year_id from data_alternative where year_id = " . $year_id);

      $alternative_ids = array();
      if(count($alternative) == 0 || count($d_alt) < count($alternative)){
        $matrix = null;
        $alternative = null;
        $rank = null;
        $criteria = null;
        $eigen_vektor = null;
        $amaks = null;
      } else {
        foreach($alternative as $key => $val){
          $alternative_ids[] = $alternative[$key]['id'];
        }

        foreach ($criteria as $key => $value) {
          $criteria_ids[] = $criteria[$key]['id'];
          $data_alternative = DataAlternative::with('alternative')->orderBy('alternative_id','asc')->where(['criteria_id' => $criteria[$key]['id'], 'year_id' => $year_id])->get();
          $matrix[$key]['criteria_id'] = $criteria[$key]['id'];
          $matrix[$key]['criteria_name'] = $criteria[$key]['criteria'];
          $matrix[$key]['result'] = $this->ahp_matrix_alternative($alternative_ids, $data_alternative);
          $sum = $this->ahp_number_of_column($alternative_ids, $matrix[$key]['result']);
          $matrix[$key]['number_of_column'] = $this->ahp_sum($alternative_ids, $sum);
          $norm_matrix = $this->ahp_norm_matrix_criteria($alternative_ids, $matrix[$key]['result'], $matrix[$key]['number_of_column']);
          $matrix[$key]['norm_matrix'] = $norm_matrix;
          $eigen_vektor = $this->ahp_eigen_vektor_alternative($alternative_ids, $matrix[$key]['norm_matrix'], $alternative);
          $eigen_vektor_id = $this->ahp_eigen_vektor_alternative_id($alternative_ids, $matrix[$key]['norm_matrix'], $alternative);
          //arsort($eigen_vektor);
          $matrix[$key]['eigen_vektor'] = $eigen_vektor;
          $matrix[$key]['eigen_vektor_id'] = $eigen_vektor_id;
          foreach($matrix[$key]['norm_matrix'] as $k => $v){
            $tests[$key][$k] = $matrix[$key]['norm_matrix'][$k][0];
          }
          $rank = $tests;
          //$test[] = $data_alternative;
        }


        $matrix_crit = $this->ahp_matrix_criteria($criteria_ids);
        $number_of_column = $this->ahp_number_of_column($criteria_ids, $matrix_crit);
        $sum = $this->ahp_sum($criteria_ids, $number_of_column);
        $norm_matrix = $this->ahp_norm_matrix_criteria($criteria_ids, $matrix_crit, $sum);
        $number_of_row = $this->ahp_number_of_row($criteria_ids, $norm_matrix);
        $eigen_vektor = $this->ahp_eigen_vektor($criteria_ids, $norm_matrix);
        $amaks = $this->ahp_amaks_alt($criteria_ids, $alternative_ids, $rank, $eigen_vektor, $alternative);

        // Store to Table Assessment Summary
        $check_sum = AssessmentSummary::with('alternative', 'year', 'salary')->where('year_id', $year_id)->get();
        if(count($check_sum) == 0){
          foreach($amaks as $key => $val){
            $a_sum[$key] = new AssessmentSummary();
            $a_sum[$key]->fill([
              'alternative_id' => $amaks[$key]['id'],
              'value' => $amaks[$key]['value'],
              'rank_salary_id' => $amaks[$key]['rank_salary_id'],
              'year_id' => $year_id,
              'created_by' => Auth::id(),
              'updated_by' => Auth::id(),
            ]);
            $a_sum[$key]->save();
          }
        } else {
          DB::table('assessment_summary')->where('year_id', $year_id)->delete();
          foreach($amaks as $key => $val){
            $a_sum[$key] = new AssessmentSummary();
            $a_sum[$key]->fill([
              'alternative_id' => $amaks[$key]['id'],
              'value' => $amaks[$key]['value'],
              'rank_salary_id' => $amaks[$key]['rank_salary_id'],
              'year_id' => $year_id,
              'created_by' => Auth::id(),
              'updated_by' => Auth::id(),
            ]);
            $a_sum[$key]->save();
          }
        }
        // Store to table Assessment Criteria
        $check_sum_criteria = AssessmentCriteria::with('alternative', 'criteria', 'year')->where('year_id', $year_id)->get();
        if(count($check_sum_criteria) == 0){
          foreach($matrix as $key => $val){
            foreach($matrix[$key]['eigen_vektor_id'] as $k => $v){
              $a_sum_crit[$k] = new AssessmentCriteria();
              $a_sum_crit[$k]->fill([
                'alternative_id' => $k,
                'criteria_id' => $matrix[$key]['criteria_id'],
                'value' => $v,
                'year_id' => $year_id,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
              ]);
              $a_sum_crit[$k]->save();
            }
          }
        } else {
          DB::table('assessment_criteria')->where('year_id', $year_id)->delete();
          foreach($matrix as $key => $val){
            foreach($matrix[$key]['eigen_vektor_id'] as $k => $v){
              $a_sum_crit[$k] = new AssessmentCriteria();
              $a_sum_crit[$k]->fill([
                'alternative_id' => $k,
                'criteria_id' => $matrix[$key]['criteria_id'],
                'value' => $v,
                'year_id' => $year_id,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
              ]);
              $a_sum_crit[$k]->save();
            }
          }
        }

      }

      $assessment_sum = AssessmentSummary::with('alternative', 'year', 'salary')->where('year_id', $year_id)->get();
      $div = Division::all();
      $year_assessment = Year::find($year_id);
      $title = 'Assessment - ' . $year_assessment->year;

      if($request->input('report') == 'report_rank'){
          return view('ahp.report-rank', compact('matrix','alternative', 'rank', 'criteria', 'eigen_vektor', 'amaks', 'title', 'year_assessment', 'assessment_sum'));
      } else {
          return view('ahp.report-divisi', compact('matrix','alternative', 'rank', 'criteria', 'eigen_vektor', 'amaks', 'title', 'year_assessment', 'assessment_sum', 'div'));
      }

      //return response(compact('matrix','alternative', 'rank', 'criteria', 'eigen_vektor', 'amaks'));
      //return view('ahp.test', compact('assessment_sum', 'div'));
    }

    public function array_sort_by_column($amaks) {

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
                 $matrix[$x][$y] = round($nilai, config('app.decimal'));
                 $matrix[$y][$x] = round((1/$nilai),config('app.decimal'));
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
          $norm_matrix[$x][$y] = round($matrix[$x][$y] / $sum[$y], config('app.decimal'));
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
          $eigen_vektor[] = round(array_sum($norm_matrix[$x])/count($criteria_id), config('app.decimal'));
      }
      return $eigen_vektor;
    }

    public function ahp_eigen_vektor_alternative($criteria_id, $norm_matrix, $alternative){
      for($x = 0; $x < count($criteria_id); $x++){
          //$eigen_vektor[$x]['name'] = $alternative[$x]['alternative'];
          //$eigen_vektor[$x]['value'] = array_sum($norm_matrix[$x])/count($criteria_id);
          $eigen_vektor[$alternative[$x]['alternative']] = round(array_sum($norm_matrix[$x])/count($criteria_id), config('app.decimal'));
      }
      arsort($eigen_vektor);
      return $eigen_vektor;
    }

    public function ahp_eigen_vektor_alternative_id($criteria_id, $norm_matrix, $alternative){
      for($x = 0; $x < count($criteria_id); $x++){
          //$eigen_vektor[$x]['name'] = $alternative[$x]['alternative'];
          //$eigen_vektor[$x]['value'] = array_sum($norm_matrix[$x])/count($criteria_id);
          $eigen_vektor[$alternative[$x]['id']] = round(array_sum($norm_matrix[$x])/count($criteria_id), config('app.decimal'));
      }
      arsort($eigen_vektor);
      return $eigen_vektor;
    }

    public function ahp_amaks($criteria_id, $matrix, $eigen_vektor){
      for($x = 0; $x <  count($criteria_id); $x++){
        for($y = 0; $y < count($criteria_id); $y++){
          $amaks[$x][$y] = $matrix[$x][$y] * $eigen_vektor[$y];
        }
      }

      for($x = 0; $x <  count($criteria_id); $x++){
          $sum_amaks[] = round(array_sum($amaks[$x]), config('app.decimal'));
      }
      return $sum_amaks;
    }

    public function ahp_amaks_alt($criteria_id, $alternative_id, $matrix, $eigen_vektor, $alternative){
      for($x = 0; $x <  count($alternative_id); $x++){
        for($y = 0; $y < count($criteria_id); $y++){
          $amaks[$x][$y] = $matrix[$y][$x] * $eigen_vektor[$y];
        }
      }

      for($x = 0; $x <  count($alternative_id); $x++){
          $sum_amaks[$x]['id'] = $alternative_id[$x];
          $sum_amaks[$x]['name'] = $alternative[$x]['alternative'];
          $sum_amaks[$x]['value'] = round(array_sum($amaks[$x]), config('app.decimal'));
      }

      $value = array();
      $rank = 1;
      foreach ($sum_amaks as $key => $row)
      {
          $value[$key]['value'] = $row['value'];
          $value[$key]['id'] = $row['id'];
          $value[$key]['name'] = $row['name'];
      }
      array_multisort($value, SORT_DESC, $sum_amaks);
      foreach ($value as $key => $row)
      {
          $value[$key]['rank'] = $rank++;
          $up_salary = RankSalary::where('rank', $value[$key]['rank'])->first();
          if($up_salary){
            $value[$key]['rank_salary_id'] = $up_salary->id;
            $value[$key]['up_salary'] = $up_salary->up_salary;
          } else {
            $value[$key]['rank_salary_id'] = 0;
            $value[$key]['up_salary'] = 0;
          }

      }
      return $value;
    }

    public function ahp_t($criteria_id, $sum_amaks, $eigen_vektor){
      for($x = 0; $x < count($criteria_id); $x++){
        $t[] = $sum_amaks[$x] / $eigen_vektor[$x];
      }

      $sum_t = round(array_sum($t) / count($criteria_id), config('app.decimal'));

      return $sum_t;
    }

    public function ahp_ci($criteria_id, $sum_t){
      $ci = round(($sum_t - count($criteria_id))/(count($criteria_id) - 1), config('app.decimal'));

      return $ci;
    }

    public function ahp_rci($criteria_id){

      $index = count($criteria_id);

      $rci = RandomConsistencyIndex::where('total_index', $index)->first();

      return $rci;

    }

    public function ahp_consitency($ci, $rci){
      $value = round($ci / $rci->index_value, config('app.decimal'));
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
    			$matrix[$x][$y] = round($data_alternative[$x]['value']/$data_alternative[$y]['value'],config('app.decimal'));
    		}
    	}
    	return $matrix;
    }
}
