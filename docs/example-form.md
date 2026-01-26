
## HTML for an example form

Forms are created in HTML. Please see form references such as <a href="https://www.w3schools.com/html/html_forms.asp">HTML Forms</a> for further information on additional field types.

Example form:

```html
<h2>{{event.name}}</h2>

<span id="llg-thank-you">
  <!-- Add your message to display after form submission here -->
  <p>Thanks for submitting the test form. We will be in contact ASAP!</p>
</span>

<!-- This area will be hidden after form submission -->
<span id="llg-booking-area">
  <!-- Note required attributes in <form> element: class="llg-form", id="llg-event-form" and method="POST" -->
	<form class="llg-form" id="llg-event-form" method="POST">

    <!-- First form input field -->
    <label for="test">Test</label>
    <!-- Note: the "name=" attribute is used as the field name in data downloads -->
    <input id="test" type="text" name="test" required />

    <!-- The primary_contact email field is used for confirmation emails -->
    <label for="email">Email address</label>
    <input id="email" type="email" name="primary_contact" required/>

    <!-- Optional email address for a participant, if present this is CC'd with the confirmation email -->
    <label for="participant_email">Participant email address</label>
    <input id="participant_email" type="email" name="participant_email" />


    <!-- Required anti spam checker, the pass phrase is set in the config.php -->
    <label for="anti_spam">Anti-spam complete the following: The founder of Quakerism has the first name: George and surname:</label>
    <input type="text" name="anti_spam" id="anti_spam" placeholder="???" required>


    <!-- Required submission button must have id="llg-send-form-btn" -->
    <input type="button" id="llg-send-form-btn" value="Send!"/>
	</form>

  <!-- Optional loading spinner -->
  <img src="{{img_url}}/spinner.gif" id="llg-spinner" alt="please wait..." />
</span>
```