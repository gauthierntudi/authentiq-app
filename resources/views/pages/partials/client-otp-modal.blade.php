{{-- Modal OTP activation client (gestion-clients + encodage) --}}
<div class="modal fade" id="otpModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:35px!important">
            <div class="modal-header text-bg-primary border-0" style="border-top-left-radius:35px!important;border-top-right-radius:35px!important">
                <h4 class="modal-title">
                    Validation OTP
                    <br>
                    <span class="fw-normal" style="font-size: .8em;">
                        Activer le compte client
                    </span>
                </h4>
            </div>
            <div class="modal-body">
                <p class="text-center mb-4" id="otpModalHint">
                    Un code OTP a été envoyé au numéro et/ou email fourni. Merci de le saisir pour activer le compte client.
                </p>
                <div class="d-flex justify-content-center gap-2 mb-3" id="otpInputsContainer">
                    @for ($i = 0; $i < 6; $i++)
                        <input type="text" class="form-control text-center otp-input" maxlength="1" data-index="{{ $i }}" inputmode="numeric" autocomplete="one-time-code" style="width: 50px; height: 50px; font-size: 24px; font-weight: bold;">
                    @endfor
                </div>
                <input type="hidden" id="otpInput">
                <div class="text-center">
                    <button type="button" class="btn btn-link btn-sm text-decoration-none" id="resendOtpBtn">
                        Renvoyer le code OTP
                    </button>
                </div>
            </div>
            <div class="modal-footer border-0 px-4">
                <button type="button" class="btn btn-light btn-lg fw-semibold" id="cancelOtpBtn" style="border-radius:12px">Annuler</button>
                <button type="button" class="btn btn-primary fw-semibold btn-lg" id="verifyOtpBtn" style="border-radius:12px">Valider OTP</button>
            </div>
        </div>
    </div>
</div>
