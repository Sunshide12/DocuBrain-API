<?php

namespace Tests\Unit;

use App\Jobs\ProcessDocumentJob;
use App\Models\Document;
use ReflectionClass;
use Tests\TestCase;

class ChunkingServiceTest extends TestCase
{
    private function invokeChunkTextByPage(array $pages, int $wordsPerChunk, int $overlapWords): array
    {
        $document = new Document;
        $job = new ProcessDocumentJob($document);

        $reflection = new ReflectionClass(ProcessDocumentJob::class);
        $method = $reflection->getMethod('chunkTextByPage');
        $method->setAccessible(true);

        return $method->invokeArgs($job, [$pages, $wordsPerChunk, $overlapWords]);
    }

    public function test_text_is_split_into_correct_number_of_chunks()
    {
        // Generate a text with 1000 words
        $words = array_map(fn ($i) => "word{$i}", range(1, 1000));
        $text = implode(' ', $words);

        $chunks = $this->invokeChunkTextByPage([1 => $text], 375, 37);

        // 1000 words, chunks of 375, overlap 37
        // C1: 375 words (0-374)
        // C2: 375 words (375 - 37 = 338 to 712)
        // C3: 375 words (713 - 37 = 676 to 1000) (wait, actually until 1000, which is 324 words)
        // Total 3 chunks.
        $this->assertCount(3, $chunks);
        $this->assertEquals(375, $chunks[0]['word_count']);
        $this->assertEquals(375, $chunks[1]['word_count']);
        $this->assertLessThanOrEqual(375, $chunks[2]['word_count']);
    }

    public function test_chunks_have_correct_overlap()
    {
        $words = array_map(fn ($i) => "word{$i}", range(1, 500));
        $text = implode(' ', $words);

        $chunks = $this->invokeChunkTextByPage([1 => $text], 375, 37);

        // The end of chunk 1 should overlap with the beginning of chunk 2
        $chunk1Words = explode(' ', $chunks[0]['content']);
        $chunk2Words = explode(' ', $chunks[1]['content']);

        $last37OfChunk1 = array_slice($chunk1Words, -37);
        $first37OfChunk2 = array_slice($chunk2Words, 0, 37);

        $this->assertEquals($last37OfChunk1, $first37OfChunk2);
    }

    public function test_empty_string_returns_empty_array()
    {
        $chunks = $this->invokeChunkTextByPage([1 => '   '], 375, 37);
        $this->assertEmpty($chunks);
    }
}
