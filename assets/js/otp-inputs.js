(() => {
    const SELECTORS = {
        container: "[data-otp-inputs]",
        hiddenValue: "[data-otp-value]",
    };

    const OTP_LENGTH = 6;

    document.querySelectorAll(SELECTORS.container).forEach((container) => {
        const inputs = container.querySelectorAll("input");
        const hiddenInput = container.nextElementSibling?.matches(
            SELECTORS.hiddenValue,
        )
            ? container.nextElementSibling
            : container.parentElement?.querySelector(SELECTORS.hiddenValue);

        if (!hiddenInput) return;

        const updateHiddenValue = () => {
            hiddenInput.value = Array.from(inputs)
                .map((input) => input.value)
                .join("");
        };

        inputs.forEach((input, index) => {
            input.addEventListener("input", (e) => {
                const value = e.target.value.replace(/[^0-9]/g, "");
                e.target.value = value.slice(0, 1);

                if (value && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }

                updateHiddenValue();
            });

            input.addEventListener("keydown", (e) => {
                if (e.key === "Backspace" && !e.target.value && index > 0) {
                    inputs[index - 1].focus();
                }
            });

            input.addEventListener("paste", (e) => {
                e.preventDefault();

                const pastedData = e.clipboardData
                    .getData("text")
                    .replace(/[^0-9]/g, "")
                    .slice(0, OTP_LENGTH);

                pastedData.split("").forEach((char, i) => {
                    if (inputs[i]) {
                        inputs[i].value = char;
                    }
                });

                const focusIndex = Math.min(
                    pastedData.length,
                    inputs.length - 1,
                );
                inputs[focusIndex].focus();

                updateHiddenValue();
            });
        });
    });
})();
