@extends('merchant.layouts.master')

@php
    use Illuminate\Support\Str;
@endphp

@push('css')
    <style>
        .copy-button {
            cursor: pointer;
        }
    </style>
@endpush

@section('breadcrumb')
    @include('merchant.components.breadcrumb',['breadcrumbs' => [
        [
            'name'  => __("Dashboard"),
            'url'   => setRoute("merchant.dashboard"),
        ]
    ], 'active' => __($page_title)])
@endsection

@section('content')
<div class="body-wrapper">
    <div class="row mb-20-none">
        <div class="col-xl-12 col-lg-12 mb-20">
            <div class="custom-card mt-10">
                <div class="dashboard-header-wrapper">
                    <h5 class="title">{{ __("developer API") }}</h5>
                </div>
                @if (session('developer_api_secret'))
                    <div class="alert alert-info mt-3">
                        <h6 class="mb-2">{{ __('Store these secrets securely. They will not be shown again.') }}</h6>
                        <ul class="mb-0 ps-3">
                            @foreach (session('developer_api_secret') as $scope => $secret)
                                <li class="mb-1">
                                    <span class="fw-semibold">{{ __(Str::title(strtolower($scope))) }}:</span>
                                    <span class="badge bg-light text-dark ms-2 align-middle">{{ $secret }}</span>
                                    <button type="button" class="btn btn-sm btn-outline-primary ms-2 copy-trigger" data-copy-value="{{ $secret }}">
                                        <i class="las la-copy"></i> {{ __('Copy') }}
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if (auth()->user()->developerApi)
                <div class="row justify-content-center">
                    <div class="col-lg-12">
                        <div class="dash-payment-item-wrapper">
                            <div class="dash-payment-item active">
                                <div class="dash-payment-title-area justify-content-end align-items-center d-sm-flex d-block">
                                    <button type="button" class="btn--base mt-3 mt-sm-0 api-kys-btn">{{ __("Create Api Keys") }}</button>
                                </div>

                                <div class="table-responsive">
                                    <table class="custom-table">
                                        <thead>
                                            <tr>
                                                <th>{{ __("name") }}</th>
                                                <th>{{ __('Client ID') }}</th>
                                                <th>{{ __('Scopes') }}</th>
                                                <th>{{ __('Mode') }}</th>
                                                <th>{{ __('Created At') }}</th>
                                                <th>{{ __('Last Used') }}</th>
                                                <th>{{ __('action') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($apis ?? [] as $item)
                                                <tr>
                                                    <td>{{ $item->name }}</td>
                                                    <td>
                                                        <div class="secret-key mt-3">
                                                            <span class="fw-bold">{{ textLength($item->client_id, 20) }}</span>
                                                            <div class="copy-text copy-trigger copytext" data-copy-value="{{ $item->client_id }}"><i class="las la-copy"></i></div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        @php
                                                            $scopeDetails = [];
                                                            $scopes = \App\Models\Merchants\DeveloperApiCredential::defaultScopes();
                                                            foreach ($scopes as $scope) {
                                                                $activeSecret = $item->secrets->where('scope', $scope)->whereNull('revoked_at')->sortByDesc('id')->first();
                                                                $revokedSecret = $item->secrets->where('scope', $scope)->whereNotNull('revoked_at')->sortByDesc('revoked_at')->first();
                                                                $scopeDetails[$scope] = [
                                                                    'active' => $activeSecret,
                                                                    'revoked' => $revokedSecret,
                                                                ];
                                                            }
                                                        @endphp
                                                        <div class="d-flex flex-column gap-2">
                                                            @foreach ($scopeDetails as $scope => $detail)
                                                                <div class="d-flex flex-column flex-md-row align-items-md-center gap-2 border rounded p-2">
                                                                    <div class="badge bg--base text-uppercase">{{ __(Str::title(strtolower($scope))) }}</div>
                                                                    <div class="flex-grow-1">
                                                                        @if ($detail['active'])
                                                                            <div class="fw-semibold">****{{ $detail['active']->secret_last_four }}</div>
                                                                            <div class="small text-muted">
                                                                                {{ $detail['active']->last_used_at ? __('Last used :time', ['time' => $detail['active']->last_used_at->diffForHumans()]) : __('Never used') }}
                                                                            </div>
                                                                        @else
                                                                            <div class="fw-semibold text-danger">{{ __('Revoked') }}</div>
                                                                            <div class="small text-muted">
                                                                                {{ $detail['revoked']?->revoked_at ? __('Revoked :time', ['time' => $detail['revoked']->revoked_at->diffForHumans()]) : '' }}
                                                                            </div>
                                                                        @endif
                                                                    </div>
                                                                    <div class="btn-group">
                                                                        <button type="button" class="btn btn-sm btn--info text-light rotate-secret-btn" data-id="{{ $item->id }}" data-scope="{{ $scope }}" data-message="{{ __('Rotate the :scope secret?', ['scope' => Str::title(strtolower($scope))]) }}"><i class="las la-sync"></i></button>
                                                                        <button type="button" class="btn btn-sm btn--danger text-light revoke-secret-btn {{ $detail['active'] ? '' : 'disabled' }}" data-id="{{ $item->id }}" data-scope="{{ $scope }}" data-message="{{ __('Revoke the :scope secret?', ['scope' => Str::title(strtolower($scope))]) }}" @if (!$detail['active']) disabled @endif><i class="las la-ban"></i></button>
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </td>
                                                    <td>{{ $item->mode }}</td>
                                                    <td>{{ dateFormat('d M Y , h:i:s A', $item->created_at) }}</td>
                                                    <td>
                                                        {{ $item->last_used_at ? dateFormat('d M Y , h:i:s A', $item->last_used_at) : __('Never') }}
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn--base btn text-light active-deactive-btn" data-id="{{ $item->id }}"><i class="las la-check-circle"></i></button>
                                                        <button type="button" class="btn--danger btn text-light delete-btn" data-id="{{ $item->id }}"><i class="las la-trash"></i></button>
                                                    </td>
                                                </tr>
                                            @empty
                                                @include('admin.components.alerts.empty2',['colspan' => 6])
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>

                                <nav>
                                    {{ $apis->links() }}
                                 </nav>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
<!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    Start fund virtual card modal
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
<div class="modal fade" id="apiKeysModal" tabindex="-1" aria-labelledby="apiKyesModalLabel" aria-hidden="true">
    <div class="modal-dialog">
    <div class="modal-content overflow-hidden">
        <div class="modal-header">
        <h5 class="modal-title" id="apiKyesModalLabel">{{ __('Create New Api Keys') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-0">
            <div class="dash-payment-item-wrapper">
                <div class="dash-payment-item active mb-0 rounded-0">
                    <div class="row mt-20">
                        <form class="card-form" action="{{ setRoute('merchant.developer.api.generate.keys') }}" method="POST">
                            @csrf
                            <input type="hidden" name="id">
                            <div class="col-xl-12 col-lg-12 form-group">
                                <label>{{ __("name") }} <span class="text--base">*</span></label>
                                <div class="input-group">
                                    <input type="text" class="form--control" required placeholder="{{ __("Enter Name") }}" name="name" value="{{ old("name") }}">
                                </div>
                            </div>
                            <div class="col-xl-12 col-lg-12 form-group">
                                <button type="submit" class="btn--base w-100 btn-loading">{{__("Create")}}<i class="las la-plus-circle ms-1"></i></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
</div>
<!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    End fund virtual card modal
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
@endsection

@push('script')
    <script>
        $(".active-deactive-btn").click(function(){
            var actionRoute =  "{{ setRoute('merchant.developer.api.mode.update') }}";
            var target      = $(this).data('id');
            var btnText     = "{{ __('Change') }}";
            var message     = "{{ __('Are you sure to change mode') }}"+"?";
            openAlertModal(actionRoute,target,message,btnText,"POST");
        });
        $(".delete-btn").click(function(){
            var actionRoute =  "{{ setRoute('merchant.developer.api.delete.keys') }}";
            var target      = $(this).data('id');
            var btnText     = "{{ __('Delete') }}";
            var message     = "{{ __('delete_text') }}"+"?";
            openAlertModal(actionRoute,target,message,btnText,"POST");
        });

        $(".rotate-secret-btn").click(function(){
            var actionRoute =  "{{ setRoute('merchant.developer.api.secret.rotate') }}";
            var target      = $(this).data('id');
            var scope       = $(this).data('scope');
            var message     = $(this).data('message');
            var btnText     = "{{ __('Rotate') }}";
            openAlertModal(actionRoute,target,message,btnText,"POST");
            setTimeout(function(){
                var modalForm = $('.white-popup .modal-alert form').last();
                if(modalForm.length){
                    modalForm.append('<input type="hidden" name="scope" value="'+scope+'">');
                }
            },200);
        });

        $(".revoke-secret-btn").click(function(){
            if($(this).hasClass('disabled')){
                return;
            }
            var actionRoute =  "{{ setRoute('merchant.developer.api.secret.revoke') }}";
            var target      = $(this).data('id');
            var scope       = $(this).data('scope');
            var message     = $(this).data('message');
            var btnText     = "{{ __('Revoke') }}";
            openAlertModal(actionRoute,target,message,btnText,"POST");
            setTimeout(function(){
                var modalForm = $('.white-popup .modal-alert form').last();
                if(modalForm.length){
                    modalForm.append('<input type="hidden" name="scope" value="'+scope+'">');
                }
            },200);
        });

        function attachCopyHandlers() {
            document.querySelectorAll('.copy-trigger').forEach(function (copyBtn) {
                copyBtn.addEventListener('click', function () {
                    var copyValue = this.getAttribute('data-copy-value');
                    if(!copyValue){
                        return;
                    }
                    var tempInput = document.createElement('input');
                    tempInput.value = copyValue;
                    document.body.appendChild(tempInput);
                    tempInput.select();
                    tempInput.setSelectionRange(0, 99999);
                    document.execCommand('copy');
                    document.body.removeChild(tempInput);
                    var message     = "{{ __('Copied Successful') }}";
                    throwMessage('success',[message]);
                });
            });
        }

        attachCopyHandlers();

        $('.api-kys-btn').on('click', function () {
            var modal = $('#apiKeysModal');
            modal.modal('show');
        });
    </script>
@endpush
