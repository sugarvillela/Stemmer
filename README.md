# Stemmer
Outputs list for word search, and traces back affixes for found roots

## Note
* I called this program a stemmer, but it is really a partial lemmatizer.  What's the difference?
* A stemmer is a quick, computationally inexpensive way of hashing several different forms of a word to a single string.  For example, a stemmer would return 'convinc' for each input 'convinces','convinced' and 'convincing'.  Though 'convinc' is not a real word, it does serve to join the various forms together. This is enough for a lot of purposes, and there are plenty of implementations of the Porter stemmer available.
* A lemmatizer goes a little more in depth, trying to reconstruct a real word and also sorting through irregular forms like 'run' and 'ran'.  
* Since this program tries to return real words, it works toward the latter task.

## Overview
* Stripping the prefixes and suffixes from a word and reconstructing its root is difficult for a computer.  Natural language is not context free, and for every rule there is an exception.  There are even exceptions for which there are no rules.  

* In light of this, a good first step is output a short list of candidate words.  You then feed the output into another program that can reference a list of known root words.  When you find a candidate word that is a root word, you call the lemmatizer's traceback function to get a list of the prefixes and suffixes were stripped off to make that word.  Put it all together and you have a valid breakdown of the word, so you're a step closer to finding its semantic meaning.

## Examples
* Say you have a made up word like **overeatery**.  Feed that to the lemmatizer and you will get this list:
> overeaterie
overeater
overeate
eaterie
overeat
eatery
eater
overe
eate
eat.

* These words are constructed from a hodgepodge of prefix and suffix rules, and of course the lemmatizer doesn't know which one is the right one.  But you see that eat is on the list, and if your reference list is thorough enough, you can find it.

* Given the root word, the traceback function returns the prefixes and suffixes
> over

> y
er

* It's no accident that the words are arranged longest to shortest.  Longer root words have precedence because fragments of long words make short words.  The longest match is the one you want.  For example, **undersecretary** returns this list:

> undersecretarie
dersecretarie
undersecretar
dersecretary
undersecrete
undersecret
dersecretar
dersecrete
secretarie
secretary
dersecret
secretar
secrete
secret

We're looking for **secretary** but many of the other words are valid too, like **secrete** and **secret**.  But those words appear after secretary on the list, so there's no chance of the wrong word being chosen.

Fragments of prefixes make up other prefixes.  For example **un** is a fragment of **under**.  To explore all possible options, the algorithm is recursive.  Thus we get **dersecretary** with the **un** removed along with **secretary** with the **under** removed.

## Suffix rules: i to y etc.
* **Loneliness** returns:
>lonelines
loneline
lonelin
lonelie
loneli
lonely
lonel
lone

* Given the root word, the traceback function returns the prefixes and suffixes
> ness

## Run the test script: stemmerTest() to see output
