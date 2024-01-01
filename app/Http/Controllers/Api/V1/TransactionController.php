<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Account;
use App\Models\Transection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Rap2hpoutre\FastExcel\FastExcel;

class TransactionController extends Controller
{
    public function getIndex(Request $request)
    {
        $transactions = Transection::latest()->paginate($request['limit'], ['*'], 'page', $request['offset']);
        $data = [
            'total' => $transactions->total(),
            'limit' => $request['limit'],
            'offset' => $request['offset'],
            'transactions' => $transactions
        ];
        return response()->json($data, 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @param Transection $transaction
     * @return JsonResponse
     */
    public function storeExpenses(Request $request, Transection $transaction): JsonResponse
    {
        $request->validate([
            'account_id' => 'required',
            'description' => 'required',
            'amount' => 'required|min:1',
        ]);
        try {
            $account = Account::find($request->account_id);
            if ($account->balance < $request->amount) {
                return response()->json(['success' => false, 'message' => 'You do not have sufficent balance'], 400);
            }
            $transaction->tran_type = "Expense";
            $transaction->account_id = $request->account_id;
            $transaction->amount = $request->amount;
            $transaction->description = $request->description;
            $transaction->debit = 0;
            $transaction->credit = 0;
            $transaction->date = $request->date;
            $transaction->save();

            $account->total_out = $account->total_out + $request->amount;
            $account->balance = $account->balance - $request->amount;
            $account->save();

            return response()->json([
                'success' => true,
                'message' => 'Expenses saved successfully',
            ], 200);
        } catch (\Throwable $th) {
            //dd($th);
            return response()->json([
                'success' => false,
                'message' => 'Expenses not saved'
            ], 403);
        }
    }

    public function fundTransfer(Request $request)
    {
        //dd($request->all());
        $request->validate([
            'account_from_id' => 'required',
            'account_to_id' => 'required',
            'description' => 'required',
            'amount' => 'required|min:1',
            'date' => 'required',
        ]);

        $acc_from = Account::find($request->account_from_id);
        if ($acc_from->balance < $request->amount) {
            return response()->json([
                'message' => 'You have not sufficient balance',
            ], 203);
        }
        $transection = new Transection;
        $transection->tran_type = 'Transfer';
        $transection->account_id = $request->account_from_id;
        $transection->amount = $request->amount;
        $transection->description = $request->description;
        $transection->debit = 1;
        $transection->credit = 0;
        $transection->balance = $acc_from->balance - $request->amount;
        $transection->date = $request->date;
        $transection->save();


        $acc_from->total_out = $acc_from->total_out + $request->amount;
        $acc_from->balance = $acc_from->balance - $request->amount;
        $acc_from->save();

        $acc_to = Account::find($request->account_to_id);
        $transection = new Transection;
        $transection->tran_type = 'Transfer';
        $transection->account_id = $request->account_to_id;
        $transection->amount = $request->amount;
        $transection->description = $request->description;
        $transection->debit = 0;
        $transection->credit = 1;
        $transection->balance = $acc_to->balance + $request->amount;
        $transection->date = $request->date;
        $transection->save();

        $acc_to->total_in = $acc_to->total_in + $request->amount;
        $acc_to->balance = $acc_to->balance + $request->amount;
        $acc_to->save();

        return response()->json([
            'message' => 'New Deposit Added successfully',
        ], 200);
    }

    public function transactionFilter(Request $request): JsonResponse
    {
        $limit = $request['limit'] ?? 10;
        $offset = $request['offset'] ?? 1;
        $transactions = Transection::when($request->has('account_id'), function ($query) use ($request) {
            $query->where('account_id', $request->account_id);
        })->when($request->has('tran_type'), function ($query) use ($request) {
            $query->where('tran_type', $request->tran_type);
        })->when($request->has('from') && $request->has('to'), function ($query) use ($request) {
            $query->whereBetween('date', [$request->from . ' 00:00:00', $request->to . ' 23:59:59']);
        })->latest()->paginate($request['limit'], ['*'], 'page', $request['offset']);
        $data = [
            'total' => $transactions->total(),
            'limit' => $limit,
            'offset' => $offset,
            'transfers' => $transactions->items()
        ];
        return response()->json($data, 200);
    }

    public function transferAccounts(Request $request)
    {

        $limit = $request['limit'] ?? 10;
        $offset = $request['offset'] ?? 1;
        if (isset($request->customer_balance)) {
            $accounts = Account::orderBy('id')->where('id', '!=', 2)->where('id', '!=', 3)->paginate($request['limit'], ['*'], 'page', $request['offset']);
            $data = [
                'limit' => $limit,
                'offset' => $offset,
                'accounts' => $accounts->items(),
                'customer_balance' => [
                    'id' => 0,
                    'account' => 'Customer Balance'
                ]
            ];
        } else {
            $accounts = Account::orderBy('id')->where('id', '!=', 2)->where('id', '!=', 3)->latest()->paginate($request['limit'], ['*'], 'page', $request['offset']);
            $data = [
                'total' => $accounts->total(),
                'limit' => $limit,
                'offset' => $offset,
                'accounts' => $accounts->items(),
            ];
        }


        return response()->json($data, 200);
    }

    public function transferListExport(Request $request)
    {
        if ($request->account_id) {
            $transactions = Transection::where('account_id', $request->account_id)->latest()->get();
        } elseif ($request->transaction_type) {
            $transactions = Transection::where('tran_type', $request->transaction_type)->latest()->get();
        } elseif ($request->from && $request->to) {
            $transactions = Transection::whereBetween('date', [$request->from . ' 00:00:00', $request->to . ' 23:59:59'])->latest()->get();
        } else {
            $transactions = Transection::where('tran_type', 'Transfer')->get();
        }
        return (new FastExcel($transactions))->download('transactions_list.xlsx');
    }

    public function transactionTypes(Request $request)
    {
        $types = Transection::select('id', 'tran_type')->groupBy('tran_type')->get();
        $data = [
            'types' => $types
        ];
        return response()->json($data, 200);
    }
}
