<?php

namespace App\Services\RAG;

class PromptBuilder
{
    /**
     * Build the final prompt for OpenAI.
     *
     * @param array $contextChunks Array of context chunks (from Pinecone or plain strings)
     * @param array $history Array of previous chat messages
     * @param string $question User question
     * @return string
     */
    public function build(array $contextChunks, array $history, string $question): string
    {
        $formattedContext = $this->formatContext($contextChunks);
        $formattedHistory = $this->formatHistory($history);

        return <<<PROMPT
            You are a helpful assistant for the organization.
            Use ONLY the information in the context.
            If the answer is not found, say "I don’t have that information."

            Context:
            $formattedContext

            Conversation:
            $formattedHistory

            Question:
            $question
            PROMPT;
    }

    /**
     * Format the context chunks into a string the model can understand.
     *
     * @param array $chunks
     * @return string
     */
    private function formatContext(array $chunks): string
    {
        return collect($chunks)
            ->map(function ($c) {
                // If chunk is an array (Pinecone format), extract text, source, url
                if (is_array($c)) {
                    $text = $c['text'] ?? '';
                    $source = $c['source'] ?? 'Unknown';
                    $url = $c['url'] ?? null;

                    return "- $text (Source: $source)" . ($url ? " [$url]" : "");
                }

                // If chunk is a string, just use it
                return "- " . (string)$c;
            })
            ->implode("\n");
    }

    /**
     * Format chat history for the prompt.
     *
     * @param array $history Array of ChatMessage models
     * @return string
     */
    private function formatHistory(array $history): string
    {
        return collect($history)
            ->map(function ($m) {
                // Ensure role is lowercase for consistency
                $role = strtolower($m->role ?? 'user');
                $content = $m->content ?? '';
                return ucfirst($role) . ": $content";
            })
            ->implode("\n");
    }
}
