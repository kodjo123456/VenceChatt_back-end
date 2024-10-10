<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class AddMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {

        return [


            'group_id' => 'required|integer|exists:groups,id', 
            'email' => 'required|string|email', 
            'sender_id' => 'required|integer|exists:users,id', 
        ];
    }
    public function messages(): array
    {
        return [


            'group_id.required' => 'Le champ ID du groupe est obligatoire.',
            'group_id.exists' => 'Le groupe spécifié n\'existe pas.',
            'email.required' => 'L\'email est obligatoire.',
            'email.email' => 'L\'email doit être une adresse email valide.',
            'sender_id.required' => 'Le champ ID de l\'expéditeur est obligatoire.',
            'sender_id.exists' => 'L\'expéditeur spécifié n\'existe pas.',
        ];
    }
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success'   => false,
            'message'   => 'Erreurs de validation',
            'data'      => $validator->errors()
        ], 422));
    }
}
