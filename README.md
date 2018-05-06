# Stemmer
Outputs list for word search, and traces back affixes for found roots

## Overview
* A stemmer is a program that tries to strip the prefixes and suffixes from a word to find its root.  This is naturally difficult for a computer.  Natural language is not context free, and for every rule there is an exception.  There are even exceptions for which there are no rules.  

* In light of this, the best a stemmer can do is output a short list of candidate words.  You then use the stemmer in conjunction with another program that can reference a list of known root words.  When you find a candidate word that is a root word, you call the stemmer's traceback function to get a list of the prefixes and suffixes were stripped off to make that word.  Put it all together and you have a valid breakdown of the word, so you're a step closer to finding its semantic meaning.

## Examples
* Say you have a made up word like **overeatery**.  Feed that to the stemmer and you will get this list:
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

* These words are constructed from a hodgepodge of prefix and suffix rules, and of course the stemmer doesn't know which one is the right one.  But you see that eat is on the list, and if your reference list is thorough enough, you can find it.

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
