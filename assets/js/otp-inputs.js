(() => {
    const SELECTORS = {
        container: "[data-otp-inputs]",
        hiddenValue: "[data-otp-value]",
    };

    const OTP_LENGTH = 6;
    const hiddenInput = document.querySelector(SELECTORS.hiddenValue);

    if (!hiddenInput) {
        console.error("OTP: Hidden input not found", SELECTORS.hiddenValue);
        return;
    }

    const containers = document.querySelectorAll(SELECTORS.container);

    if (containers.length === 0) {
        console.error("OTP: No container found", SELECTORS.container);
        return;
    }

    containers.forEach((container) => {
        const inputs = container.querySelectorAll("input");

        if (inputs.length === 0) {
            console.error("OTP: No inputs found in container");
            return;
        }

        if (inputs.length !== OTP_LENGTH) {
            console.error(
                "OTP: Expected",
                OTP_LENGTH,
                "inputs, found",
                inputs.length,
            );
        }

        const updateHiddenValue = () => {
            const value = Array.from(inputs)
                .map((input) => input.value)
                .join("");
            hiddenInput.value = value;

            if (value.length === OTP_LENGTH && hiddenInput.value !== value) {
                console.error("OTP: Failed to set hidden input value");
            }
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
