/**
 *  Pages Authentication
 */

'use strict';
const formAuthentication = document.querySelector('#formAuthentication');

document.addEventListener('DOMContentLoaded', function (e) {
  (function () {
    // Form validation for Add new record
    if (formAuthentication) {
      const fields = {};
      const hasUsername = formAuthentication.querySelector('[name="username"]');
      const hasEmail = formAuthentication.querySelector('[name="email"]');
      const hasEmailUsername = formAuthentication.querySelector('[name="email-username"]');
      const hasPassword = formAuthentication.querySelector('[name="password"]');
      const hasConfirmPassword = formAuthentication.querySelector('[name="confirm-password"]');
      const hasTerms = formAuthentication.querySelector('[name="terms"]');

      if (hasUsername) {
        fields.username = {
          validators: {
            notEmpty: {
              message: 'Please enter username'
            },
            stringLength: {
              min: 6,
              message: 'Username must be more than 6 characters'
            }
          }
        };
      }

      if (hasEmail) {
        fields.email = {
          validators: {
            notEmpty: {
              message: 'Please enter your email'
            },
            emailAddress: {
              message: 'Please enter valid email address'
            }
          }
        };
      }

      if (hasEmailUsername) {
        fields['email-username'] = {
          validators: {
            notEmpty: {
              message: 'Please enter email / username'
            }
          }
        };
      }

      if (hasPassword) {
        const passwordValidators = {
          notEmpty: {
            message: 'Please enter your password'
          }
        };

        if (hasConfirmPassword) {
          passwordValidators.stringLength = {
            min: 6,
            message: 'Password must be more than 6 characters'
          };
        }

        fields.password = {
          validators: passwordValidators
        };
      }

      if (hasConfirmPassword) {
        fields['confirm-password'] = {
          validators: {
            notEmpty: {
              message: 'Please confirm password'
            },
            identical: {
              compare: function () {
                return formAuthentication.querySelector('[name="password"]').value;
              },
              message: 'The password and its confirm are not the same'
            },
            stringLength: {
              min: 6,
              message: 'Password must be more than 6 characters'
            }
          }
        };
      }

      if (hasTerms) {
        fields.terms = {
          validators: {
            notEmpty: {
              message: 'Please agree terms & conditions'
            }
          }
        };
      }

      const fv = FormValidation.formValidation(formAuthentication, {
        fields,
        plugins: {
          trigger: new FormValidation.plugins.Trigger(),
          bootstrap5: new FormValidation.plugins.Bootstrap5({
            eleValidClass: '',
            rowSelector: '.mb-3'
          }),
          submitButton: new FormValidation.plugins.SubmitButton(),

          defaultSubmit: new FormValidation.plugins.DefaultSubmit(),
          autoFocus: new FormValidation.plugins.AutoFocus()
        },
        init: instance => {
          instance.on('plugins.message.placed', function (e) {
            if (e.element.parentElement.classList.contains('input-group')) {
              e.element.parentElement.insertAdjacentElement('afterend', e.messageElement);
            }
          });
        }
      });
    }

    //  Two Steps Verification
    const numeralMask = document.querySelectorAll('.numeral-mask');

    // Verification masking
    if (numeralMask.length) {
      numeralMask.forEach(e => {
        new Cleave(e, {
          numeral: true
        });
      });
    }
  })();
});
