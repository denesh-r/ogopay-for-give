// console.log('ogopay.js loaded');
// if (ogopay_params) console.log(ogopay_params);

// create the zoid component that gets loaded inside the modal in an iframe
window.MyZoidComponent = zoid.create({

	// The html tag used to render my component
	tag: 'my-component',

	url: 'https://test-ipg.ogo.exchange/defaulthostedpage',
	// url: 'http://localhost:3000/defaulthostedpage',

	dimensions: {
		width: '100%',
		height: '100%'
	},

	autoResize: true,

	props: {
		merchantId: {
			type: 'string',
			required: true,
			queryParam: true
		},

		customerId: {
			type: 'string',
			required: true,
			queryParam: true
		},

		returnUrl: {
			type: 'string',
			required: true,
			queryParam: true
		},

		orderId: {
			type: 'string',
			required: true,
			queryParam: true
		},

		page: {
			type: 'string',
			required: false,
			queryParam: true
		},

		amount: {
			type: 'string',
			required: true,
			queryParam: true
		},

		time: {
			type: 'string',
			required: true,
			queryParam: true
		},

		hash: {
			type: 'string',
			required: true,
			queryParam: true
		}

	},

});

const urlParams = new URLSearchParams(window.location.search);
const mode = urlParams.get('payment-mode');
const key = urlParams.get('key');


// prepare the modal to display iframe from the checkout page
var prepareForCheckout = function () {

	if (key) {
		jQuery.post(
			ogopay_params.url, // The hook URL
			{ key: key }, // get order details of this orderId
			function (response) {

				// console.log(response)
				// console.log('showing ogo dialog');

				// show the modal dialog
				jQuery('#myModal').css('display', 'block');

				// when the close button on the dialog is clicked...
				jQuery("#modalClose").on('click', function () {
					
					// hide the dialog
					jQuery('#myModal').css('display', 'none');

					// clear the contents of the iframe container when closing
					// else the iframes get rendered repeatedly on multiple clicks
					jQuery('#cont').empty();

				});

				// render the iframe
				MyZoidComponent({
					merchantId: response.merchantId,
					customerId: response.customerId,
					returnUrl: response.returnUrl,
					page: 'zoid',
					orderId: response.orderId,
					amount: response.amount,
					time: response.time,
					hash: response.hash
				}).render('#cont');
			}
		);
	}
}

// this is the parent function that gets called when we get redirected inside the dialog to the close_modal page
function close_modal(url){
	jQuery("#modalClose").click();
	window.location.replace(url);
}

if (mode == 'ogopay') {
	prepareForCheckout();
}