import * as React from "react";
import { useImperativeHandle, useRef } from "react";
import { Tooltip } from "@mui/material";
import { getTranslations } from "../../../../../functions/translations";
import {
  FormValidInterface,
  InputInterface,
} from "../../../../../interface/InputInterface";
import { handleChangeInputGeneric } from "../../../../../functions/form";
import { stringValidator } from "../../../../../functions/validator";
import { TOKEN } from "../../../../../token";

let translations: any = getTranslations();

export type ArDesignInputState = {
  defaultValue: string | null;
};

type FormState = {
  arDesign: InputInterface;
};

export const ArDesignInput = React.forwardRef(
  ({ defaultValue }: ArDesignInputState, ref) => {
    const getDefaultValue = (): FormState => {
      return {
        arDesign: { value: defaultValue ?? "" },
      };
    };
    const [values, setValues] = React.useState<FormState>(getDefaultValue());
    const inputRef = useRef<any>(null);
    const inputNameSelector = 'input[name="post_title"]';

    const handleChange =
      (prop: keyof FormState) =>
      (event: React.ChangeEvent<HTMLInputElement>) => {
        handleChangeInputGeneric(event, prop, setValues);
      };

    const isValid = async () => {
      const error = await stringValidator({
        value: values.arDesign.value,
        maxLength: 69,
        canBeEmpty: false,
      });
      setValues((v) => {
        return {
          ...v,
          arDesign: {
            ...v.arDesign,
            error: error,
          },
        };
      });
      return error === "";
    };

    useImperativeHandle(ref, () => ({
      async isValid(): Promise<FormValidInterface> {
        const valid = await isValid();
        return {
          valid: valid,
          details: [
            {
              valid: valid,
              ref: ref,
              dRef: inputRef,
            },
          ],
        };
      },
    }));

    React.useEffect(() => {
      isValid();
      $(inputNameSelector).val(values.arDesign.value);
      if (values.arDesign.value) {
        $(inputNameSelector)
          .parent()
          .find("label")
          .addClass("screen-reader-text");
      } else {
        $(inputNameSelector)
          .parent()
          .find("label")
          .removeClass("screen-reader-text");
      }
    }, [values.arDesign.value]); // eslint-disable-line react-hooks/exhaustive-deps

    React.useEffect(() => {
      const handleChange = (event: JQuery.TriggeredEvent) => {
        const target = event.target as HTMLInputElement;
        const value = target.value;
        setValues((v) => ({
          ...v,
          arDesign: {
            ...v.arDesign,
            value: value,
          },
        }));
      };

      $(document).on("input", inputNameSelector, handleChange);

      return () => {
        $(document).off("input", inputNameSelector, handleChange);
      };
    }, []);

    return (
      <>
        <label
          htmlFor={`_${TOKEN}_arDesign`}
          style={{
            display: "block",
          }}
        >
          <Tooltip title={"arDesign"} arrow placement="top">
            <span>{translations["fArticles"]["arDesign"]}</span>
          </Tooltip>
        </label>
        <div style={{ display: "flex" }}>
          <div style={{ flex: 1 }}>
            <input
              id={`_${TOKEN}_arDesign`}
              name={`_${TOKEN}_arDesign`}
              type={"text"}
              value={values.arDesign.value}
              onChange={handleChange("arDesign")}
              style={{ width: "100%" }}
              ref={inputRef}
            />
          </div>
        </div>
        {values.arDesign.error && (
          <div className={`${TOKEN}_error_field`}>{values.arDesign.error}</div>
        )}
      </>
    );
  },
);
