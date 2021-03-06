<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\MediaUploadingTrait;
use App\Http\Requests\MassDestroyTransactionRequest;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Models\Room;
use App\Models\Transaction;
use App\Models\User;
use Gate;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

class TransactionController extends Controller
{
    use MediaUploadingTrait;

    public function index()
    {
        abort_if(Gate::denies('transaction_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $transactions = Transaction::all();

        return view('admin.transactions.index', compact('transactions'));
    }

    public function create()
    {
        abort_if(Gate::denies('transaction_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $users = User::all()->pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $rooms = Room::all()->pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.transactions.create', compact('users', 'rooms'));
    }

    public function store(StoreTransactionRequest $request)
    {
        $transaction = Transaction::create($request->all());

        if ($request->input('payment_proof', false)) {
            $transaction->addMedia(storage_path('tmp/uploads/' . $request->input('payment_proof')))->toMediaCollection('payment_proof');
        }

        if ($media = $request->input('ck-media', false)) {
            Media::whereIn('id', $media)->update(['model_id' => $transaction->id]);
        }

        return redirect()->route('admin.transactions.index');
    }

    public function edit(Transaction $transaction)
    {
        abort_if(Gate::denies('transaction_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $users = User::all()->pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $rooms = Room::all()->pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $transaction->load('user', 'room');

        return view('admin.transactions.edit', compact('users', 'rooms', 'transaction'));
    }

    public function update(UpdateTransactionRequest $request, Transaction $transaction)
    {
        $transaction->update($request->all());

        if ($request->input('payment_proof', false)) {
            if (!$transaction->payment_proof || $request->input('payment_proof') !== $transaction->payment_proof->file_name) {
                if ($transaction->payment_proof) {
                    $transaction->payment_proof->delete();
                }

                $transaction->addMedia(storage_path('tmp/uploads/' . $request->input('payment_proof')))->toMediaCollection('payment_proof');
            }
        } elseif ($transaction->payment_proof) {
            $transaction->payment_proof->delete();
        }

        return redirect()->route('admin.transactions.index');
    }

    public function show(Transaction $transaction)
    {
        abort_if(Gate::denies('transaction_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $transaction->load('user', 'room', 'transactionFeedback');

        return view('admin.transactions.show', compact('transaction'));
    }

    public function destroy(Transaction $transaction)
    {
        abort_if(Gate::denies('transaction_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $transaction->delete();

        return back();
    }

    public function massDestroy(MassDestroyTransactionRequest $request)
    {
        Transaction::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function storeCKEditorImages(Request $request)
    {
        abort_if(Gate::denies('transaction_create') && Gate::denies('transaction_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $model         = new Transaction();
        $model->id     = $request->input('crud_id', 0);
        $model->exists = true;
        $media         = $model->addMediaFromRequest('upload')->toMediaCollection('ck-media');

        return response()->json(['id' => $media->id, 'url' => $media->getUrl()], Response::HTTP_CREATED);
    }
}