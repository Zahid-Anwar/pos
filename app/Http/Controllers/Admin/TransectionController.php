<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Transection;
use App\Models\Account;
use App\CPU\Helpers;
use Rap2hpoutre\FastExcel\FastExcel;
use Carbon\Carbon;

class TransectionController extends Controller
{
    public function list(Request $request)
    {
        $accounts = Account::orderBy('id','desc')->get();
        $acc_id = $request['account_id'];
        $tran_type = $request['tran_type'];
        $from = $request['from'];
        $to = $request['to'];

        $query = Transection::when($acc_id!=null, function($q) use ($request){
                                    return $q->where('account_id',$request['account_id']);
                                })
                                ->when($tran_type!=null, function($q) use ($request){
                                    return $q->where('tran_type',$request['tran_type']);
                                })
                                ->when($from!=null, function($q) use ($request){
                                    return $q->whereBetween('date', [$request['from'], $request['to']]);
                                });

        $transections = $query->orderBy('id','desc')->paginate(Helpers::pagination_limit())->appends(['account_id' => $request['account_id'],'tran_type'=>$request['tran_type'],'from'=>$request['from'],'to'=>$request['to']]);
        return view('admin-views.transection.list',compact('accounts','transections','acc_id','tran_type','from','to'));
    }
    public function export(Request $request)
    {
        //return $request;
        $acc_id = $request['account_id'];
        $tran_type = $request['tran_type'];
        $from = $request['from'];
        $to = $request['to'];
        if($acc_id==null && $tran_type==null && $to==null && $from !=null)
        {
            $transections = Transection::whereMonth('date',Carbon::now()->month)->get();

        }else{
            $transections = Transection::when($acc_id!=null, function($q) use ($request){
                                    return $q->where('account_id',$request['account_id']);
                                })
                                ->when($tran_type!=null, function($q) use ($request){
                                    return $q->where('tran_type',$request['tran_type']);
                                })
                                ->when($from!=null, function($q) use ($request){
                                    return $q->whereBetween('date', [$request['from'], $request['to']]);
                                })->get();
        }

        $storage = [];
        foreach($transections as $transection)
        {
            array_push($storage,[
                'transection_type'=> $transection->tran_type,
                'account' => $transection->account->account,
                'amount'=> $transection->amount,
                'description'=> $transection->description,
                'debit'=> $transection->debit==1?$transection->amount:0,
                'credit'=>$transection->credit==1?$transection->amount:0,
                'balance'=>$transection->balance,
                'date'=>$transection->date,
            ]);
        }
        return (new FastExcel($storage))->download('transection_history.xlsx');
    }
}
