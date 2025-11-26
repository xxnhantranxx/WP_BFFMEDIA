jQuery(document).ready(function ($) {
	// Validate NumberPhone field
    function validatePhone(element) {
		$(element).each(function () {
		  $(
			`
			  <div class="validator_text">
				  <span id="valid-msg" class="hide">
				  </span><span id="error-msg" class="hide"></span>
			  </div>
			  `
		  ).insertAfter($(this));
		});
	}
	validatePhone("#billing_phone");
  	const inputPhone = $("#billing_phone")[0];
	const iti = window.intlTelInput(inputPhone, {
		allowDropdown: true,
		autoPlaceholder: "off",
		containerClass: "phoneInputBill",
		initialCountry: "us",
		formatAsYouType: true,
		formatOnDisplay: true,
		separateDialCode: true,
		customPlaceholder: function (
		selectedCountryPlaceholder,
		selectedCountryData
		) {
		return "e.g. " + selectedCountryPlaceholder;
		},
 	 });
	window.iti = iti; // useful for testing

	const errorMsg = $("#error-msg");
	const validMsg = $("#valid-msg");
	const errorMap = [
		"Incorrect phone number",
		"Invalid country code",
		"Phone number is too short",
		"Phone number is too long",
		"Incorrect phone number",
	];

	const reset = () => {
		$("#billing_phone").removeClass("error");
		errorMsg.html("").addClass("hide");
		validMsg.html("").addClass("hide");
	};

	const showError = (msg) => {
		$("#billing_phone").addClass("error");
		errorMsg.html(msg).removeClass("hide");
	};

	// on blur: validate
	$("#billing_phone").on("blur", function () {
		reset();
		const phoneValue = $(this).val().trim();
		if (!phoneValue) {
		showError("Required");
		} else if (iti.isValidNumber()) {
		// validMsg.html("Valid number: " + iti.getNumber()).removeClass("hide");
		// $("#billing_phone").val(iti.getNumber())
		} else {
		const errorCode = iti.getValidationError();
		const msg = errorMap[errorCode] || "Incorrect phone number";
		showError(msg);
		}
	});

	// on keyup / change flag: reset
	$("#billing_phone").on("keyup change", reset);

	// Hàm để cập nhật mã quốc gia cho billing_phone
	const updateCountryCode = () => {
		// Lấy mã quốc gia từ giá trị của billing_country
		const selectedCountryCode = $("#billing_country").val().toLowerCase();
		// Đặt lại quốc gia trong trường billing_phone
		iti.setCountry(selectedCountryCode);
	};

	// Gọi hàm updateCountryCode ngay khi trang load để cập nhật mã quốc gia ban đầu
	updateCountryCode();
	// Theo dõi sự thay đổi của trường billing_country
	$("#billing_country").on("change", function () {
		// Lấy mã quốc gia từ giá trị của billing_country
		const selectedCountryCode = $(this).val().toLowerCase();
		// Đặt lại quốc gia trong trường billing_phone
		iti.setCountry(selectedCountryCode);
	});

	// Reset lại trạng thái khi người dùng nhập số điện thoại mới
	$("#billing_phone").on("keyup change", function () {
		iti.setNumber($(this).val());
	});

	// Kiểm tra khi form được submit
	$("form.checkout").on("submit", function () {
		// Nếu cần có thể validate số điện thoại trước khi gửi
		if (!iti.isValidNumber()) {
		alert("Incorrect phone number");
		return false; // Dừng submit form nếu không hợp lệ
		}
	});
});
