<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ContactController extends Controller
{
    /**
     * Display a listing of the contacts.
     */
    public function index()
    {
        $contacts = Contact::latest()->get();
    
        return response()->json([
            'status' => 200,
            'message' => 'Lấy Thông Tin Thành Công',
            'data' => $contacts
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created contact in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:contacts,email',
            'phone' => 'nullable|string|max:20',
            'message' => 'nullable|string',
            'address' => 'nullable|string|max:255',
        ]);

        $contact = Contact::create($request->all());

        return response()->json([
            'status' => 201,
            'message' => 'Thêm Thành Công',
            'data' => $contact
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified contact.
     */
    public function show(Contact $contact)
    {
        return response()->json([
            'status' => 200,
            'message' => 'Lấy thông tin thành công',
            'data' => $contact
        ], Response::HTTP_OK);
    }

    /**
     * Update the specified contact in storage.
     */
    public function update(Request $request, Contact $contact)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:contacts,email,' . $contact->id,
            'phone' => 'nullable|string|max:20',
            'message' => 'nullable|string',
            'address' => 'nullable|string|max:255',
            'status' => 'sometimes|required|in:Đã xử lý,Chưa xử lý,Không xử lý'
        ]);

        $contact->update($request->all());

        return response()->json([
            'status' => 200,
            'message' => 'Updated Thông Tin Thành Công',
            'data' => $contact
        ], Response::HTTP_OK);
    }

    /**
     * Remove the specified contact from storage.
     */
    public function destroy(Contact $contact)
    {
        $contact->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Xoá Thông Tin Liên Hệ Thành Công!'
        ], Response::HTTP_OK);
    }
}
