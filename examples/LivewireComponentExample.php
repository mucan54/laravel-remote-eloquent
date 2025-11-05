<?php

namespace App\Livewire;

use App\Models\Post;
use Livewire\Component;
use Livewire\WithPagination;
use RemoteEloquent\Client\Exceptions\RemoteQueryException;

/**
 * Example Livewire Component using Remote Eloquent
 *
 * This component runs in the NativePHP mobile app and queries
 * data from the remote Laravel backend.
 */
class PostList extends Component
{
    use WithPagination;

    public string $search = '';
    public string $status = 'published';
    public string $sortBy = 'created_at';
    public string $sortDirection = 'desc';
    public ?string $error = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => 'published'],
        'sortBy' => ['except' => 'created_at'],
    ];

    public function render()
    {
        try {
            $posts = Post::query()
                // Eager load relationships to avoid N+1
                ->with(['user', 'comments'])

                // Search filter
                ->when($this->search, function($query) {
                    $query->where('title', 'like', "%{$this->search}%")
                          ->orWhere('content', 'like', "%{$this->search}%");
                })

                // Status filter
                ->where('status', $this->status)

                // Sorting
                ->orderBy($this->sortBy, $this->sortDirection)

                // Pagination
                ->paginate(20);

            $this->error = null;

        } catch (RemoteQueryException $e) {
            $this->error = 'Failed to load posts. Please try again.';

            logger()->error('Remote query failed', [
                'error' => $e->getMessage(),
                'status_code' => $e->getStatusCode(),
                'context' => $e->getContext(),
            ]);

            $posts = collect([]);
        }

        return view('livewire.post-list', [
            'posts' => $posts,
        ]);
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatus()
    {
        $this->resetPage();
    }

    public function sortBy(string $column)
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }
}
