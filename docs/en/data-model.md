# Data model

We have left it up to you to decide how payments are linked in with your existing model.

Here are a few ideas:

 * MyObject has_many Payments - allowing for partial payments to be made
 * MyObject has_one Payment
 * ...or you could generate payments and complete them in a stand alone form.