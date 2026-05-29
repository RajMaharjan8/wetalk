interface InputFieldProps {
  placeholder: string;
  value: string;
  isPassword: boolean;
  onChangeFunction: (value: string) => void;
  error: string;
}
export default function InputField({
  placeholder,
  value,
  isPassword,
  error,
  onChangeFunction,
}: InputFieldProps) {
  return (
    <>
      <div className="w-full border border-[#ddd] rounded-lg p-2 outline-primary has-[input:focus-within]:outline-2 bg-white">
        <input
          placeholder={placeholder}
          value={value}
          type={isPassword ? "password" : "text"}
          className="w-full focus:outline-none"
          onChange={(e) => onChangeFunction(e.target.value)}
        />
      </div>
      <p className="text-red-400">{error}</p>
    </>
  );
}
