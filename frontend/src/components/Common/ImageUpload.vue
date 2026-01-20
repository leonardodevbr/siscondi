<template>
  <div class="relative">
    <div
      :class="[
        'relative border-2 border-dashed rounded-lg overflow-hidden cursor-pointer transition-colors',
        imagePreview ? 'border-slate-300' : 'border-slate-300 hover:border-blue-400',
        sizeClass,
      ]"
      @click="triggerFileInput"
    >
      <input
        ref="fileInput"
        type="file"
        accept="image/*"
        class="hidden"
        @change="handleFileSelect"
      />

      <div v-if="!imagePreview" class="flex flex-col items-center justify-center h-full p-4 text-slate-400">
        <CameraIcon class="h-8 w-8 mb-2" />
        <span class="text-xs text-center">Adicionar Foto</span>
      </div>

      <div v-else class="relative h-full w-full">
        <img
          :src="imagePreview"
          alt="Preview"
          class="h-full w-full object-cover"
          @error="handleImageError"
        />
        <button
          type="button"
          @click.stop="removeImage"
          class="absolute top-1 right-1 bg-red-600 text-white rounded-full p-1 hover:bg-red-700 transition-colors z-10"
          title="Remover imagem"
        >
          <XMarkIcon class="h-4 w-4" />
        </button>
        <div
          v-if="imageError"
          class="absolute inset-0 flex flex-col items-center justify-center bg-slate-100 text-slate-400"
        >
          <PhotoIcon class="h-8 w-8 mb-1" />
          <span class="text-xs text-center px-2">Imagem inválida</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, watch } from 'vue';
import { CameraIcon, XMarkIcon, PhotoIcon } from '@heroicons/vue/24/outline';

export default {
  name: 'ImageUpload',
  components: {
    CameraIcon,
    XMarkIcon,
    PhotoIcon,
  },
  props: {
    modelValue: {
      type: [File, String, null],
      default: null,
    },
    size: {
      type: String,
      default: 'md',
      validator: (value) => ['sm', 'md', 'lg'].includes(value),
    },
  },
  emits: ['update:modelValue'],
  setup(props, { emit }) {
    const fileInput = ref(null);
    const imagePreview = ref(null);
    const imageError = ref(false);

    const sizeClass = computed(() => {
      const sizes = {
        sm: 'h-24 w-24',
        md: 'h-32 w-32',
        lg: 'h-40 w-40',
      };
      return sizes[props.size] || sizes.md;
    });

    const isValidImageUrl = (url) => {
      if (!url || typeof url !== 'string') return false;
      
      if (url.includes('via.placeholder.com')) {
        return false;
      }
      
      if (url.startsWith('http://') || url.startsWith('https://')) {
        try {
          new URL(url);
          return true;
        } catch {
          return false;
        }
      }
      
      return true;
    };

    const updatePreview = (value) => {
      imageError.value = false;
      
      if (value instanceof File) {
        const reader = new FileReader();
        reader.onload = (e) => {
          imagePreview.value = e.target.result;
        };
        reader.readAsDataURL(value);
      } else if (typeof value === 'string' && value) {
        if (!isValidImageUrl(value)) {
          imagePreview.value = null;
          return;
        }
        
        if (value.startsWith('http://') || value.startsWith('https://')) {
          imagePreview.value = value;
        } else if (value.startsWith('products/') || value.startsWith('product-variants/')) {
          imagePreview.value = `/storage/${value}`;
        } else {
          imagePreview.value = value;
        }
      } else {
        imagePreview.value = null;
      }
    };

    const handleImageError = () => {
      imageError.value = true;
    };

    watch(
      () => props.modelValue,
      (newValue) => {
        updatePreview(newValue);
      },
      { immediate: true }
    );

    const triggerFileInput = () => {
      fileInput.value?.click();
    };

    const handleFileSelect = (event) => {
      const file = event.target.files?.[0];
      if (file && file.type.startsWith('image/')) {
        emit('update:modelValue', file);
      }
    };

    const removeImage = () => {
      imageError.value = false;
      emit('update:modelValue', null);
      if (fileInput.value) {
        fileInput.value.value = '';
      }
    };

    return {
      fileInput,
      imagePreview,
      imageError,
      sizeClass,
      triggerFileInput,
      handleFileSelect,
      removeImage,
      handleImageError,
    };
  },
};
</script>
