<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Input;
use App\V1\Models\CriteriaComparison;
use App\V1\Models\Criteria;
use App\V1\Models\ImportanceLevel;
use Redirect;

class CriteriaComparisonController extends Controller
{
  private $criteria_comparison;

  public function __construct(){
    $this->criteria_comparison = new CriteriaComparison();
    $this->middleware('auth');
  }

  public function index(){
    $criteria_comparison = $this->criteria_comparison->with('criteria1', 'criteria2', 'importance_level')->get();
    $res['result'] = $criteria_comparison;
    return view('criteria_comparison.index', compact('criteria_comparison'));
    //return response($res);
  }

  public function store(Request $request){
    $this->criteria_comparison->fill([
      'criteria_id_1' => $request->input('criteria_id_1'),
      'criteria_id_2' => $request->input('criteria_id_2'),
      'value' => $request->input('value'),
    ]);
    $this->criteria_comparison->save();
    return Redirect::route('criteria_comparison.index');
  }

  public function create(){
    $criteria = Criteria::all();
    $importance_level = ImportanceLevel::all();
    return view('criteria_comparison.form', compact('criteria', 'importance_level'));
  }

  public function edit($id){
    $criteria_comparison = $this->criteria_comparison->find($id);

    $criteria = Criteria::all();
    $importance_level = ImportanceLevel::all();

    return view('criteria_comparison.form_update', compact('criteria_comparison', 'criteria', 'importance_level'));
  }

  public function update(Request $request, $id){
    $criteria_comparison = $this->criteria_comparison->find($id);
    $criteria_comparison->criteria_id_1 = $request->input('criteria_id_1');
    $criteria_comparison->criteria_id_2 = $request->input('criteria_id_2');
    $criteria_comparison->value = $request->input('value');
    $criteria_comparison->save();
    return Redirect::route('criteria_comparison.index');
  }

  public function show($id){
    $criteria_comparison = $this->criteria_comparison->find($id);
    return view('criteria_comparison.show', compact('criteria_comparison'));
  }

  public function destroy($id){
    $criteria_comparison = $this->criteria_comparison->find($id);
    $criteria_comparison->delete();
    return Redirect::route('criteria_comparison.index');
  }
}
