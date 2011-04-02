<?php
/**
 * Copyright (c) 2008, David R. Nadeau, NadeauSoftware.com.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *	* Redistributions of source code must retain the above copyright
 *	  notice, this list of conditions and the following disclaimer.
 *
 *	* Redistributions in binary form must reproduce the above
 *	  copyright notice, this list of conditions and the following
 *	  disclaimer in the documentation and/or other materials provided
 *	  with the distribution.
 *
 *	* Neither the names of David R. Nadeau or NadeauSoftware.com, nor
 *	  the names of its contributors may be used to endorse or promote
 *	  products derived from this software without specific prior
 *	  written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY
 * WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY
 * OF SUCH DAMAGE.
 */

/*
 * This is a BSD License approved by the Open Source Initiative (OSI).
 * See:  http://www.opensource.org/licenses/bsd-license.php
 */


/**
 * Strip punctuation characters from UTF-8 text.
 *
 * Characters stripped from the text include characters in the following
 * Unicode categories:
 *
 * 	Separators
 * 	Control characters
 *	Formatting characters
 *	Surrogates
 *	Open and close quotes
 *	Open and close brackets
 *	Dashes
 *	Connectors
 *	Numer separators
 *	Spaces
 *	Other punctuation
 *
 * Exceptions are made for punctuation characters that occur withn URLs
 * (such as [ ] : ; @ & ? and others), within numbers (such as . , % # '),
 * and within words (such as - and ').
 *
 * Parameters:
 * 	text		the UTF-8 text to strip
 *
 * Return values:
 * 	the stripped UTF-8 text.
 *
 * See also:
 * 	http://nadeausoftware.com/articles/2007/9/php_tip_how_strip_punctuation_characters_web_page
 */
function strip_punctuation( $text )
{
	$urlbrackets    = '\[\]\(\)';
	$urlspacebefore = ':;\'_\*%@&?!' . $urlbrackets;
	$urlspaceafter  = '\.,:;\'\-_\*@&\/\\\\\?!#' . $urlbrackets;
	$urlall         = '\.,:;\'\-_\*%@&\/\\\\\?!#' . $urlbrackets;

	$specialquotes = '\'"\*<>';

	$fullstop      = '\x{002E}\x{FE52}\x{FF0E}';
	$comma         = '\x{002C}\x{FE50}\x{FF0C}';
	$arabsep       = '\x{066B}\x{066C}';
	$numseparators = $fullstop . $comma . $arabsep;

	$numbersign    = '\x{0023}\x{FE5F}\x{FF03}';
	$percent       = '\x{066A}\x{0025}\x{066A}\x{FE6A}\x{FF05}\x{2030}\x{2031}';
	$prime         = '\x{2032}\x{2033}\x{2034}\x{2057}';
	$nummodifiers  = $numbersign . $percent . $prime;

	return preg_replace(
		array(
		// Remove separator, control, formatting, surrogate,
		// open/close quotes.
			'/[\p{Z}\p{Cc}\p{Cf}\p{Cs}\p{Pi}\p{Pf}]/u',
		// Remove other punctuation except special cases
			'/\p{Po}(?<![' . $specialquotes .
				$numseparators . $urlall . $nummodifiers . '])/u',
		// Remove non-URL open/close brackets, except URL brackets.
			'/[\p{Ps}\p{Pe}](?<![' . $urlbrackets . '])/u',
		// Remove special quotes, dashes, connectors, number
		// separators, and URL characters followed by a space
			'/[' . $specialquotes . $numseparators . $urlspaceafter .
				'\p{Pd}\p{Pc}]+((?= )|$)/u',
		// Remove special quotes, connectors, and URL characters
		// preceded by a space
			'/((?<= )|^)[' . $specialquotes . $urlspacebefore . '\p{Pc}]+/u',
		// Remove dashes preceded by a space, but not followed by a number
			'/((?<= )|^)\p{Pd}+(?![\p{N}\p{Sc}])/u',
		// Remove consecutive spaces
			'/ +/',
		),
		' ',
		$text );
}