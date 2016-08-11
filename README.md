# GoogleTagManager

## Configuring Enhanced Ecommerce

The 10,000 foot view of this process is:

1. Enable Enhanced Ecommerce in Google Analytics
1. Setup the required data layer variables in Google Tag Manager
1. Setup the required triggers in Google Tag Manager
1. Setup the required tags in Google Tag Manager
1. Use the Preview mode in Google Tag Manager to verify the right tags are firing on the right pages
1. Wait for transactions to be tracked in Google Analytics

### Enable Enhanced Ecommerce in Google Analytics

The first thing we need to do is ensure that the Google Analytics account has Enhanced Ecommerce enabled.

Login to Analytics and select the property to modify.

Navigate to the Admin area and select 'E-commerce settings' for the appropriate view.

Under 'E-commerce setup', toggle the switch to 'On'

Now we switch our attention to Google Tag Manager. Login to Google Tag Manager.

### Setup the required data layer variables in Google Tag Manager

_If these variables are already defined, you can skip this step_

Navigate to Variables > User-Defined Variables > New

Name the variable, e.g. `pageType`

In step 1, select 'Data Layer Variable'

In step 2:

1. Enter `pageType` in the Data Layer Variable Name box
1. Select 'Version 2' in the 'Data Layer Version'

Save the variable

### Setup the required triggers in Google Tag Manager

Navigate to Triggers > New

Enter a meaningful name for this trigger, e.g. `Checkout Success Page`

In step 1, 'Choose Event', select `Page View`

In step 2, 'Configure Trigger', select `Page View` from the 'Trigger type' drop-down

In step 3, 'Fire On':

1. Select the `pageType` variable we just created from the first drop-down
1. Select `equals` from the second drop-down
1. Enter `checkout_onepage_success` in the third box

Save the trigger

### Setup the required tags in Google Tag Manager

We need two tags: A tag which appears on every page, like standard Analytics, and a tag which will only trigger on pages we define

#### Create the Analytics tag

Browse to Tags > New.

Enter a meaningful name for this tag.

In step 1, 'Choose Product', select 'Google Analytics'

In step 2, 'Choose a Tag Type', select 'Universal Analytics' (unless you're sure the customer is using the older 'Classic Analytics')

In step 3, 'Configure Tag':

1. Enter the customers Analytics Tracking ID in the 'Tracking ID' box
1. Select `Page View` from the 'Track Type' drop-down.
1. Expand 'Ecommerce Features' and check the `Enable Enhanced Ecommerce Features` and `Use data layer` checkboxes

In step 4, 'Fire On', select `All Pages`

Now save the tag

#### Create the transaction tracking tag

We need to create a new tracking tag which will fire on the checkout success page

Browse to Tags > New

Enter a meaningful name for this tag, e.g. `Enhance Ecommerce Tracking - Checkout Success Page`

In step 1, 'Choose Product', select `Google Analytics`

In step 2, 'Choose a Tag Type', select `Universal Analytics` (unless you're sure the customer is using the older `Classic Analytics`)

In step 3, 'Configure Tag':

1. Enter the customers Analytics Tracking ID in the 'Tracking ID' box
1. Select `Transaction` from the 'Track Type' drop-down.

In step 4, 'Fire On', select the `Checkout Success Page` trigger which we created earlier

Now save the tag

## Use the Preview mode in Google Tag Manager to verify the right tags are firing on the right pages

Once you've set everything up, you can select the down arrow to the right of the 'Preview' button in the top right 
of the screen and select 'Preview' mode. Open the website in a separate tag and go through checkout. The preview pane
at the bottom tells you which tags are fired on which page and for which event and also what data Tag Manager has detected

You can go back into Google Tag Manager and update the tags, variables and triggers to fine-tune or debug the container,
just remember to click the 'Refresh' link on the 'Overview' screen before going back to the client website.
 
### Troubleshooting

Ensure that the IP address isn't being excluded, filtered or blocked by checking the settings 
in Analytics > Admin > Property > Tracking Info and Analytics > Admin > View > Filters
