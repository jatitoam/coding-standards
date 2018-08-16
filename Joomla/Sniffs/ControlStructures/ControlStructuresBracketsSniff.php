<?php
/**
 * Joomla! Coding Standard
 *
 * @package    Joomla.CodingStandard
 * @copyright  Copyright (C) 2015 Open Source Matters, Inc. All rights reserved.
 * @license    http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License Version 2 or Later
 */

/**
 * Control Structures Brackets Test.
 *
 * Checks if the declaration of control structures is correct.
 * Curly brackets must be on a line by their own.
 *
 * @since     1.0
 */
class Joomla_Sniffs_ControlStructures_ControlStructuresBracketsSniff implements PHP_CodeSniffer_Sniff
{
	/**
	 * The number of spaces code should be indented.
	 *
	 * @var integer
	 */
	public $indent = 4;


	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array
	 */
	public function register()
	{
		return array(
				T_IF,
				T_ELSEIF,
				T_ELSE,
				T_FOREACH,
				T_FOR,
				T_SWITCH,
				T_DO,
				T_WHILE,
				T_TRY,
				T_CATCH,
				T_FINALLY,
			   );
	}

	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * @param   PHP_CodeSniffer_File  $phpcsFile  The file being scanned.
	 * @param   int                   $stackPtr   The position of the current token in the stack passed in $tokens.
	 *
	 * @return  void
	 */
	public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
	{
		$tokens    = $phpcsFile->getTokens();
		$errorData = array(strtolower($tokens[$stackPtr]['content']));

		if (isset($tokens[$stackPtr]['scope_opener']) === false)
		{
			if ($tokens[$stackPtr]['code'] !== T_WHILE)
			{
				$error = 'Possible parse error: %s missing opening or closing brace';
				$phpcsFile->addWarning($error, $stackPtr, 'MissingBrace', $errorData);
			}

			return;
		}

		$openBrace            = $tokens[$stackPtr]['scope_opener'];
		$lastContent          = $phpcsFile->findPrevious(T_WHITESPACE, ($openBrace - 1), $stackPtr, true);
		$controlStructureLine = $tokens[$lastContent]['line'];
		$braceLine            = $tokens[$openBrace]['line'];

		if ($braceLine === $controlStructureLine)
		{
			// Excludes alternative syntax like ```if ($a == true) : ``` which is usually used for templating with HTML
			if ($tokens[$openBrace]['code'] === "PHPCS_T_COLON")
			{
				$phpcsFile->recordMetric($stackPtr, 'Rule ignored because alternative syntax is used', 'same line');

				return;
			}

			$phpcsFile->recordMetric($stackPtr, 'Control Structure opening brace placement', 'same line');
			$error = 'Opening brace of a %s must be on the line after the definition';
			$fix   = $phpcsFile->addFixableError($error, $openBrace, 'OpenBraceNewLine', $errorData);

			if ($fix === true)
			{
				$phpcsFile->fixer->beginChangeset();

				if ($tokens[($openBrace - 1)]['code'] === T_WHITESPACE)
				{
					$phpcsFile->fixer->replaceToken(($openBrace - 1), '');
				}

				$phpcsFile->fixer->addNewlineBefore($openBrace);
				$phpcsFile->fixer->endChangeset();
			}

			return;
		}
		else
		{
			$phpcsFile->recordMetric($stackPtr, 'Control Structure opening brace placement', 'new line');

			if ($braceLine > ($controlStructureLine + 1))
			{
				$error = 'Opening brace of a %s must be on the line following the %s declaration.; Found %s line(s).';
				$data  = array(
						  $tokens[$stackPtr]['content'],
						  $tokens[$stackPtr]['content'],
						  ($braceLine - $controlStructureLine - 1),
						 );
				$fix   = $phpcsFile->addFixableError($error, $openBrace, 'OpenBraceWrongLine', $data);

				if ($fix === true)
				{
					$phpcsFile->fixer->beginChangeset();

					for ($i = ($openBrace - 1); $i > $lastContent; $i--)
					{
						if ($tokens[$i]['line'] === ($tokens[$openBrace]['line'] + 1))
						{
							break;
						}

						$phpcsFile->fixer->replaceToken($i, '');
					}

					$phpcsFile->fixer->endChangeset();
				}

				return;
			}
		}

		if ($tokens[($openBrace + 1)]['content'] !== $phpcsFile->eolChar)
		{
			$error = 'Opening %s brace must be on a line by itself.';
			$fix   = $phpcsFile->addFixableError($error, $openBrace, 'OpenBraceNotAlone', $errorData);

			if ($fix === true)
			{
				$phpcsFile->fixer->addNewline($openBrace);
			}
		}

		if ($tokens[($openBrace - 1)]['code'] === T_WHITESPACE)
		{
			$prevContent = $tokens[($openBrace - 1)]['content'];

			if ($prevContent === $phpcsFile->eolChar)
			{
				$spaces = 0;
			}
			else
			{
				$blankSpace = substr($prevContent, strpos($prevContent, $phpcsFile->eolChar));
				$spaces = 0;

				/**
				 * A tab is only counted with strlen as 1 character but we want to count
				 * the number of spaces so add 4 characters for a tab otherwise the strlen
				 */
				for ($i = 0; $length = strlen($blankSpace), $i < $length; $i++)
				{
					if ($blankSpace[$i] === "\t")
					{
						$spaces += $this->indent;
					}
					else
					{
						$spaces += strlen($blankSpace[$i]);
					}
				}
			}

			// Anonymous classes and functions set the indent at one plus their own indent level.
			if ($phpcsFile->hasCondition($stackPtr, T_CLOSURE) === true
				|| $phpcsFile->hasCondition($stackPtr, T_ANON_CLASS) === true)
			{
				$expected = ($tokens[$stackPtr]['level'] + 1) * $this->indent;
			}
			else
			{
				$expected = $tokens[$stackPtr]['level'] * $this->indent;
			}

			// We need to divide by 4 here since there is a space vs tab intent in the check vs token
			$expected /= $this->indent;
			$spaces   /= $this->indent;

			$closure = in_array('PHPCS_T_CLOSURE', $tokens[$stackPtr]['conditions']);

			$lastStr = $phpcsFile->findPrevious(T_STRING, $stackPtr);

			/*
			 * If we're inside a closure that's written directly as a function parameter and not stored in a variable
			 * add 1 to the expected number of tabs to avoid false-positive 'SpaceBeforeBrace' error
			 */
			if ($tokens[$lastStr + 1]['type'] === 'T_OPEN_PARENTHESIS'
				&& $closure
				&& $spaces !== $expected
			)
			{
				$expected += 1;
			}

			if ($spaces !== $expected)
			{
				$error = 'Expected %s tabs before opening brace; %s found';
				$data  = array(
						  $expected,
						  $spaces,
						 );
				$fix   = $phpcsFile->addFixableError($error, $openBrace, 'SpaceBeforeBrace', $data);

				if ($fix === true)
				{
					$indent = str_repeat("\t", $expected);

					if ($spaces === 0)
					{
						$phpcsFile->fixer->addContentBefore($openBrace, $indent);
					}
					else
					{
						$phpcsFile->fixer->replaceToken(($openBrace - 1), $indent);
					}
				}
			}
		}

		// A single newline after opening brace (i.e. brace in on a line by itself), remove extra newlines.
		if (isset($tokens[$stackPtr]['scope_opener']) === true)
		{
			$opener = $tokens[$stackPtr]['scope_opener'];

			for ($next = ($opener + 1); $next < $phpcsFile->numTokens; $next++)
			{
				$code = $tokens[$next]['code'];

				if ($code === T_WHITESPACE)
				{
					continue;
				}

				// Skip all empty tokens on the same line as the opener.
				if ($tokens[$next]['line'] === $tokens[$opener]['line']
					&& (isset(PHP_CodeSniffer_Tokens::$emptyTokens[$code]) === true
					|| $code === T_CLOSE_TAG)
				)
				{
					continue;
				}

				// We found the first bit of a code, or a comment on the following line.
				break;
			}

			$found = ($tokens[$next]['line'] - $tokens[$opener]['line']);

			if ($found > 1)
			{
				$error = 'Expected 1 newline after opening brace; %s found';
				$data  = array($found);
				$fix   = $phpcsFile->addFixableError($error, $opener, 'ExtraNewlineAfterOpenBrace', $data);

				if ($fix === true)
				{
					$phpcsFile->fixer->beginChangeset();

					for ($i = ($opener + 1); $i < $next; $i++)
					{
						if ($found > 0 && $tokens[$i]['line'] === $tokens[$next]['line'])
						{
							break;
						}

						$phpcsFile->fixer->replaceToken($i, '');
					}

					$phpcsFile->fixer->addContent($opener, $phpcsFile->eolChar);
					$phpcsFile->fixer->endChangeset();
				}
			}
		}
	}
}
